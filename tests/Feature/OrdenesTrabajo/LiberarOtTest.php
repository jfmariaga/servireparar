<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Enums\RolPrioridad;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\Prioridad;
use App\Models\SolicitudInsumoOt;
use App\Models\Tecnico;
use App\Models\User;
use App\Services\OrdenTrabajo\EstadoOtService;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 12 · Fase 12.3 — "Liberar OT" (D12) y notificaciones diferidas (D13 / H27):
 * al crear no se avisa a técnicos ni a Bodega; al liberar sí. La OT no se congela.
 */
class LiberarOtTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    /** @return array{0: \App\Models\OrdenTrabajo, 1: User, 2: Inventario} */
    private function otConInsumo(bool $conInsumo = true): array
    {
        $cliente = Cliente::factory()->create();
        $tecnicoUser = User::factory()->create(['estado' => 'activo']);
        $tecnico = Tecnico::factory()->conSueldo()->create(['usuario_id' => $tecnicoUser->id]);
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 50]);

        $ot = app(OrdenTrabajoService::class)->crear($this->jefeDeTaller(), [
            'cliente_id' => $cliente->id,
            'prioridad_id' => Prioridad::where('nombre', 'Media')->value('id'),
            'descripcion' => 'OT liberar',
            'tiempo_estimado_dias' => 5,
        ], [[
            'descripcion' => 'Tarea 1',
            'tecnico_id' => $tecnico->id,
            'insumos' => $conInsumo ? [['inventario_id' => $item->id, 'cantidad' => 4]] : [],
        ]]);

        return [$ot, $tecnicoUser, $item];
    }

    public function test_el_estado_inicial_se_llama_planificacion(): void
    {
        [$ot] = $this->otConInsumo(false);

        $this->assertSame('en_revision', $ot->estado->slug);
        $this->assertSame('Planificación', $ot->estado->nombre);
    }

    public function test_crear_no_avisa_a_tecnicos_ni_a_bodega_pero_reserva_stock(): void
    {
        $almacen = $this->usuarioConRol(RolPrioridad::Almacenista->value);
        [$ot, $tecnicoUser, $item] = $this->otConInsumo();

        $this->assertSame(0, $tecnicoUser->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $almacen->fresh()->unreadNotifications()->count());
        $this->assertSame(1, SolicitudInsumoOt::where('ot_id', $ot->id)->where('estado', 'pendiente')->count());
        $this->assertSame(46.0, (float) $item->fresh()->disponible());
    }

    public function test_liberar_avisa_a_los_tecnicos_de_la_ot(): void
    {
        [$ot, $tecnicoUser] = $this->otConInsumo(false);

        app(EstadoOtService::class)->liberar($ot, $this->jefeDeTaller());

        $this->assertSame('en_revision', 'en_revision'); // guard: slug intacto
        $this->assertSame('pendiente', $ot->fresh()->estado->slug);
        $this->assertSame('OT liberada para ejecución', $tecnicoUser->fresh()->notifications()->first()?->data['titulo']);
    }

    public function test_liberar_con_insumos_pendientes_avisa_a_bodega(): void
    {
        $almacen = $this->usuarioConRol(RolPrioridad::Almacenista->value);
        [$ot] = $this->otConInsumo(true);

        app(EstadoOtService::class)->liberar($ot, $this->jefeDeTaller());

        $this->assertTrue(
            $almacen->fresh()->notifications()->where('data->titulo', 'Insumos pendientes de una OT liberada')->exists(),
        );
    }

    public function test_liberar_sin_insumos_no_avisa_a_bodega(): void
    {
        $almacen = $this->usuarioConRol(RolPrioridad::Almacenista->value);
        [$ot] = $this->otConInsumo(false);

        app(EstadoOtService::class)->liberar($ot, $this->jefeDeTaller());

        $this->assertSame(0, $almacen->fresh()->unreadNotifications()->count());
    }

    public function test_la_ot_no_se_congela_al_liberar(): void
    {
        [$ot] = $this->otConInsumo(false);
        app(EstadoOtService::class)->liberar($ot, $this->jefeDeTaller());

        $ot = $ot->fresh(['estado']);
        $this->assertFalse($ot->estaBloqueada());

        app(OrdenTrabajoService::class)->corregir($ot, $this->jefeDeTaller(), ['descripcion' => 'Alcance corregido']);
        $this->assertSame('Alcance corregido', $ot->fresh()->descripcion);
    }

    public function test_alias_planificar_sigue_funcionando(): void
    {
        [$ot] = $this->otConInsumo(false);

        app(EstadoOtService::class)->planificar($ot, $this->jefeDeTaller());

        $this->assertSame('pendiente', $ot->fresh()->estado->slug);
    }
}
