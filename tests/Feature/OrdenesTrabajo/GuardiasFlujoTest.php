<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Models\Inventario;
use App\Models\SolicitudInsumoOt;
use App\Models\Tecnico;
use App\Services\OrdenTrabajo\EstadoOtService;
use App\Models\PrestamoHerramienta;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use App\Services\OrdenTrabajo\PrestamoHerramientaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Guardias de la máquina de estados: planificación/liberación obligatoria (H6),
 * una tarea con insumos sin entregar NO se puede iniciar (Phase 13 / D21),
 * reapertura invalida la salida aprobada (H7), cancelación de OT / tarea (D8).
 */
class GuardiasFlujoTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    public function test_no_se_ejecuta_una_ot_sin_planificar(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tarea = $ot->tareas()->first();
        $tecnico = $this->tecnicoUser();

        $this->assertTrue($tecnico->cannot('executeTareas', $ot->fresh(['estado'])));

        app(EstadoOtService::class)->planificar($ot, $this->jefeDeTaller());
        $this->assertSame('pendiente', $ot->fresh()->estado->slug);
        $this->assertTrue($tecnico->can('executeTareas', $ot->fresh(['estado'])));
    }

    public function test_no_se_inicia_una_tarea_con_insumo_sin_entregar(): void
    {
        $ot = $this->crearOt(tareas: 1);
        app(EstadoOtService::class)->liberar($ot, $this->jefeDeTaller());
        $tecnicoUser = $this->tecnicoUser();
        $tecnico = Tecnico::factory()->conSueldo()->create(['usuario_id' => $tecnicoUser->id]);
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 50, 'costo_unitario' => 100]);
        $item->movimientos()->create(['tipo_mov' => 'entrada', 'cantidad' => 50, 'cantidad_disponible' => 50, 'costo_unitario' => 100, 'fecha' => now(), 'usuario_id' => $tecnicoUser->id, 'origen' => 'entrada_proveedor']);

        $tarea = app(OrdenTrabajoService::class)->agregarTarea($ot->fresh(['estado']), $this->jefeDeTaller(), [
            'descripcion' => 'Con insumo', 'tecnico_id' => $tecnico->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 2]],
        ]);

        // Insumo pendiente en Bodega → el técnico no puede iniciar.
        Volt::actingAs($tecnicoUser)
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot->fresh()])
            ->call('iniciarTarea', $tarea->id, app(EstadoOtService::class));
        $this->assertSame('pendiente', $tarea->fresh()->estado_tarea);
        $this->assertTrue($tarea->fresh()->bloqueadaPorInsumos());

        // Bodega entrega → ya se puede iniciar.
        app(\App\Services\OrdenTrabajo\AtencionInsumoOtService::class)
            ->entregar(SolicitudInsumoOt::where('detalle_ot_id', $tarea->id)->firstOrFail(), $this->usuarioConRol('Almacenista'));

        Volt::actingAs($tecnicoUser)
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot->fresh()])
            ->call('iniciarTarea', $tarea->id, app(EstadoOtService::class));
        $this->assertSame('en_curso', $tarea->fresh()->estado_tarea);
    }

    public function test_un_insumo_rechazado_tambien_bloquea_el_inicio(): void
    {
        $ot = $this->crearOt(tareas: 1);
        app(EstadoOtService::class)->liberar($ot, $this->jefeDeTaller());
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 50]);

        $tarea = app(OrdenTrabajoService::class)->agregarTarea($ot->fresh(['estado']), $this->jefeDeTaller(), [
            'descripcion' => 'Con insumo rechazado', 'tecnico_id' => Tecnico::factory()->conSueldo()->create()->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 2]],
        ]);
        SolicitudInsumoOt::where('detalle_ot_id', $tarea->id)->update(['estado' => 'rechazada', 'motivo_rechazo' => 'Se compra directo']);

        $this->assertTrue($tarea->fresh()->bloqueadaPorInsumos(), 'Un insumo rechazado deja la tarea sin poder iniciarse.');
    }

    public function test_finalizar_tarea_sin_insumos_es_directo(): void
    {
        $ot = $this->crearOt(tareas: 1);
        app(EstadoOtService::class)->liberar($ot, $this->jefeDeTaller());
        $tarea = $ot->tareas()->first();
        $tarea->update(['estado_tarea' => 'en_curso', 'fecha_inicio' => now()]);
        $this->adjuntarEvidenciaTarea($tarea);

        $tarea = app(OrdenTrabajoService::class)->finalizarTareaOperario($tarea->fresh(), $this->jefeDeTaller(), 1);
        $this->assertSame('finalizada', $tarea->estado_tarea);
    }

    public function test_reabrir_una_ot_finalizada_invalida_la_salida_aprobada(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $ot->update(['valor_proyecto' => 500_000, 'salida_estado' => 'aprobada']);
        $this->finalizarTodasLasTareas($ot);
        $this->completarChecklist($ot);
        app(EstadoOtService::class)->recalcular($ot->fresh());
        $this->assertSame('finalizada', $ot->fresh()->estado->slug);

        app(EstadoOtService::class)->reabrir($ot->fresh(['estado']), $this->jefeDeTaller(), 'Trabajo adicional');

        $ot->refresh();
        $this->assertSame('en_curso', $ot->estado->slug);
        $this->assertSame('no_solicitada', $ot->salida_estado);
        $this->assertDatabaseHas('ot_eventos', ['ot_id' => $ot->id, 'tipo' => 'correccion', 'descripcion' => 'Salida de equipo (aprobada) invalidada: la OT volvió a ejecución.']);
    }

    public function test_cancelar_tarea_libera_insumos_y_recalcula(): void
    {
        $ot = $this->crearOt(tareas: 2);
        $tecnico = Tecnico::factory()->conSueldo()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 50]);
        $tarea = app(OrdenTrabajoService::class)->agregarTarea($ot, $this->jefeDeTaller(), [
            'descripcion' => 'A cancelar',
            'tecnico_id' => $tecnico->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 4]],
        ]);

        app(OrdenTrabajoService::class)->cancelarTarea($tarea->fresh(), $this->jefeDeTaller(), 'Cliente desistió de esta parte');

        $this->assertSame('cancelada', $tarea->fresh()->estado_tarea);
        $this->assertSame('cancelada', SolicitudInsumoOt::where('detalle_ot_id', $tarea->id)->value('estado'));
        $this->assertEquals(50, $item->fresh()->disponible(), 'La reserva se libera');
    }

    public function test_no_se_cancela_la_unica_tarea_activa(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $this->expectException(ValidationException::class);
        app(OrdenTrabajoService::class)->cancelarTarea($ot->tareas()->first(), $this->jefeDeTaller(), 'motivo');
    }

    public function test_cancelar_la_ot_la_cierra_y_libera_reservas(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tecnico = Tecnico::factory()->conSueldo()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 20]);
        app(OrdenTrabajoService::class)->agregarTarea($ot, $this->jefeDeTaller(), [
            'descripcion' => 'X', 'tecnico_id' => $tecnico->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 5]],
        ]);

        app(OrdenTrabajoService::class)->cancelarOt($ot->fresh(['estado']), $this->jefeDeTaller(), 'Proyecto anulado');

        $ot->refresh();
        $this->assertSame('cancelada', $ot->estado->slug);
        $this->assertTrue($ot->estado->es_terminal);
        $this->assertEquals(20, $item->fresh()->disponible());
        $this->assertSame(0, SolicitudInsumoOt::where('ot_id', $ot->id)->where('estado', 'pendiente')->count());
    }

    public function test_el_jefe_ya_no_puede_cancelar_una_ot_liberada_con_trabajo_realizado(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $jefe = $this->jefeDeTaller();
        $admin = $this->administrador();

        // Antes de liberar, el Jefe todavía puede cancelar.
        $this->assertTrue($jefe->can('cancel', $ot));

        app(EstadoOtService::class)->liberar($ot->fresh(['estado']), $jefe);
        $ot->tareas()->first()->update(['estado_tarea' => 'en_curso', 'fecha_inicio' => now()]);

        $this->assertFalse($jefe->can('cancel', $ot->fresh(['estado'])), 'El Jefe ya no debería poder cancelar con trabajo realizado.');
        $this->assertTrue($admin->can('cancel', $ot->fresh(['estado'])), 'El Administrador siempre puede cancelar.');
    }

    public function test_el_jefe_puede_cancelar_una_ot_liberada_sin_trabajo_realizado(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $jefe = $this->jefeDeTaller();

        app(EstadoOtService::class)->liberar($ot->fresh(['estado']), $jefe);

        $this->assertTrue($jefe->can('cancel', $ot->fresh(['estado'])), 'Liberada pero sin tareas en curso o finalizadas, el Jefe sí puede cancelar.');
    }

    public function test_un_prestamo_de_herramienta_sin_devolver_no_bloquea_cancelar_la_ot(): void
    {
        // Phase 12 / D15: las herramientas están fuera del ciclo de la OT.
        $ot = $this->crearOt(tareas: 1);
        $tecnico = $ot->tareas()->first()->tecnico;
        $tool = Inventario::factory()->herramienta()->create();
        app(PrestamoHerramientaService::class)->entregar(
            app(PrestamoHerramientaService::class)->solicitar($tecnico, $tool, $ot->tareas()->first()),
            $this->usuarioConRol('Almacenista'),
        );

        app(OrdenTrabajoService::class)->cancelarOt($ot->fresh(['estado']), $this->jefeDeTaller(), 'motivo');

        $this->assertSame('cancelada', $ot->fresh()->estado->slug);
        $this->assertSame('entregada', PrestamoHerramienta::first()->estado, 'El préstamo sigue vivo tras cancelar la OT.');
    }
}
