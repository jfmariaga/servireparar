<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Enums\RolPrioridad;
use App\Mail\OtEntregadaCliente;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\SolicitudInsumoOt;
use App\Models\Tecnico;
use App\Models\User;
use App\Notifications\OtNotificacion;
use App\Services\OrdenTrabajo\AtencionInsumoOtService;
use App\Services\OrdenTrabajo\EstadoOtService;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use App\Services\OrdenTrabajo\SalidaEquipoService;
use App\Services\OrdenTrabajo\VencimientoOtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Phase 11 / Fase 6 — avisos in-app entre roles y correo al cliente (D5 / FR-011).
 */
class NotificacionesOtTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    private function conRol(RolPrioridad $rol): User
    {
        return $this->usuarioConRol($rol->value);
    }

    private function noLeidas(User $u): int
    {
        return $u->unreadNotifications()->count();
    }

    public function test_crear_ot_avisa_a_los_jefes_de_taller(): void
    {
        $jefe = $this->conRol(RolPrioridad::JefeDeTaller);
        $this->crearOt(tareas: 1);

        $this->assertSame(1, $this->noLeidas($jefe->fresh()));
        $this->assertSame('Nueva OT por planificar', $jefe->notifications()->first()->data['titulo']);
    }

    public function test_nueva_solicitud_de_insumo_avisa_al_almacen(): void
    {
        $almacen = $this->conRol(RolPrioridad::Almacenista);
        $ot = $this->crearOt(tareas: 1);
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 50]);

        app(OrdenTrabajoService::class)->agregarTarea($ot, $this->jefeDeTaller(), [
            'descripcion' => 'X', 'tecnico_id' => Tecnico::factory()->conSueldo()->create()->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 2]],
        ]);

        $this->assertGreaterThanOrEqual(1, $this->noLeidas($almacen->fresh()));
    }

    public function test_entregar_insumo_avisa_al_tecnico_de_la_tarea(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tecnicoUser = $this->conRol(RolPrioridad::Tecnico);
        $tecnico = Tecnico::factory()->conSueldo()->create(['usuario_id' => $tecnicoUser->id]);
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 50, 'costo_unitario' => 100]);
        $item->movimientos()->create(['tipo_mov' => 'entrada', 'cantidad' => 50, 'cantidad_disponible' => 50, 'costo_unitario' => 100, 'fecha' => now(), 'usuario_id' => $tecnicoUser->id, 'origen' => 'entrada_proveedor']);

        app(OrdenTrabajoService::class)->agregarTarea($ot, $this->jefeDeTaller(), [
            'descripcion' => 'Cambio', 'tecnico_id' => $tecnico->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 3]],
        ]);
        $solicitud = SolicitudInsumoOt::where('ot_id', $ot->id)->firstOrFail();

        app(AtencionInsumoOtService::class)->entregar($solicitud, $this->conRol(RolPrioridad::Almacenista));

        $this->assertSame('Insumo disponible', $tecnicoUser->fresh()->notifications()->first()->data['titulo']);
    }

    public function test_salida_solicitada_avisa_al_administrador(): void
    {
        $admin = $this->conRol(RolPrioridad::Administrador);
        $ot = $this->crearOt(tareas: 1);
        $ot->update(['valor_proyecto' => 500_000]);
        $this->finalizarTodasLasTareas($ot);
        $this->completarChecklist($ot);
        app(EstadoOtService::class)->recalcular($ot->fresh());

        app(SalidaEquipoService::class)->solicitar($ot->fresh(['estado']), $this->jefeDeTaller());

        $this->assertSame('Salida de equipo por aprobar', $admin->fresh()->notifications()->first()->data['titulo']);
    }

    public function test_entrega_notifica_al_creador_y_envia_correo_al_cliente(): void
    {
        Mail::fake();
        $cliente = Cliente::factory()->create(['correo' => 'cliente@ejemplo.com']);
        $jefe = $this->jefeDeTaller();
        $ot = $this->crearOt(['cliente_id' => $cliente->id, 'valor_proyecto' => 500_000], tareas: 1);
        $creador = $ot->creadoPor;
        $this->finalizarTodasLasTareas($ot);
        $this->completarChecklist($ot);
        app(EstadoOtService::class)->recalcular($ot->fresh());

        $salida = app(SalidaEquipoService::class);
        $salida->solicitar($ot->fresh(['estado']), $jefe);
        $salida->aprobar($ot->fresh(['estado']), $this->conRol(RolPrioridad::Administrador));
        $salida->confirmarEntrega($ot->fresh(['estado']), $jefe, 'Firma');

        Mail::assertSent(OtEntregadaCliente::class, fn ($m) => $m->hasTo('cliente@ejemplo.com'));
        $this->assertTrue($creador->fresh()->notifications()->where('data->titulo', 'OT entregada')->exists());
    }

    public function test_el_aviso_de_vencimiento_no_se_repite(): void
    {
        $this->crearOt(['tiempo_estimado_dias' => 0.1], tareas: 1);

        $this->assertSame(1, app(VencimientoOtService::class)->revisar());
        $this->assertSame(0, app(VencimientoOtService::class)->revisar(), 'No re-notifica');
        $this->assertSame(1, app(VencimientoOtService::class)->revisar(reenviar: true), 'Con --reenviar sí');
    }

    public function test_la_campana_lista_las_no_leidas_y_las_marca(): void
    {
        $jefe = $this->jefeDeTaller();
        $this->crearOt(tareas: 1);

        $comp = Volt::actingAs($jefe->fresh())->test('notificaciones.campana');
        $comp->assertSee('Nueva OT por planificar');

        $comp->call('marcarLeidas');
        $this->assertSame(0, $this->noLeidas($jefe->fresh()));
    }
}
