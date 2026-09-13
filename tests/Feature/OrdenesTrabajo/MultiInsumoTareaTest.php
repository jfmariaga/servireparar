<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Models\Inventario;
use App\Models\SolicitudInsumoOt;
use App\Models\Tecnico;
use App\Services\OrdenTrabajo\CosteoOtService;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 11 / D6 — una tarea de OT puede tener N líneas de insumo, cada una con
 * su propia solicitud hacia Bodega, y quitar una línea deja traza.
 */
class MultiInsumoTareaTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    public function test_una_tarea_genera_una_solicitud_por_cada_linea_de_insumo(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tecnico = Tecnico::factory()->conSueldo()->create();
        $a = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 100]);
        $b = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 100]);

        app(OrdenTrabajoService::class)->agregarTarea($ot, $this->jefeDeTaller(), [
            'descripcion' => 'Cambio de kit',
            'tecnico_id' => $tecnico->id,
            'insumos' => [
                ['inventario_id' => $a->id, 'cantidad' => 2],
                ['inventario_id' => $b->id, 'cantidad' => 5],
            ],
        ]);

        $tarea = $ot->tareas()->latest('id')->first();
        $this->assertCount(2, $tarea->insumos);
        $this->assertSame(2, SolicitudInsumoOt::where('detalle_ot_id', $tarea->id)->where('estado', 'pendiente')->count());
    }

    public function test_editar_la_cantidad_de_una_linea_deja_evento_insumo_modificado(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tecnico = Tecnico::factory()->conSueldo()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 100]);

        $tarea = app(OrdenTrabajoService::class)->agregarTarea($ot, $this->jefeDeTaller(), [
            'descripcion' => 'Tarea',
            'tecnico_id' => $tecnico->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 3]],
        ]);

        app(OrdenTrabajoService::class)->actualizarTarea($tarea->fresh(), $this->jefeDeTaller(), [
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 8]],
        ]);

        $this->assertDatabaseHas('ot_eventos', ['ot_id' => $ot->id, 'tipo' => 'insumo_modificado']);
        $this->assertEquals(8, (float) SolicitudInsumoOt::where('detalle_ot_id', $tarea->id)->value('cantidad'));
    }

    public function test_quitar_una_linea_cancela_su_solicitud_y_deja_traza(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tecnico = Tecnico::factory()->conSueldo()->create();
        $a = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 100]);
        $b = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 100]);

        $tarea = app(OrdenTrabajoService::class)->agregarTarea($ot, $this->jefeDeTaller(), [
            'descripcion' => 'Tarea',
            'tecnico_id' => $tecnico->id,
            'insumos' => [
                ['inventario_id' => $a->id, 'cantidad' => 2],
                ['inventario_id' => $b->id, 'cantidad' => 2],
            ],
        ]);

        app(OrdenTrabajoService::class)->actualizarTarea($tarea->fresh(), $this->jefeDeTaller(), [
            'insumos' => [['inventario_id' => $a->id, 'cantidad' => 2]],
        ]);

        $this->assertDatabaseHas('solicitudes_insumo_ot', [
            'detalle_ot_id' => $tarea->id,
            'inventario_id' => $b->id,
            'estado' => 'cancelada',
        ]);
        $this->assertDatabaseHas('ot_eventos', ['ot_id' => $ot->id, 'tipo' => 'insumo_cancelado']);
        $this->assertSame(1, $tarea->fresh()->insumos()->count());
    }

    public function test_reactivar_una_linea_rechazada_deja_traza_aunque_no_cambie(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tecnico = Tecnico::factory()->conSueldo()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 100]);

        $tarea = app(OrdenTrabajoService::class)->agregarTarea($ot, $this->jefeDeTaller(), [
            'descripcion' => 'Tarea',
            'tecnico_id' => $tecnico->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 3]],
        ]);

        SolicitudInsumoOt::where('detalle_ot_id', $tarea->id)->update(['estado' => 'rechazada', 'motivo_rechazo' => 'No hay']);

        // Se reenvía la MISMA cantidad/ítem — antes esto reactivaba en silencio.
        app(OrdenTrabajoService::class)->actualizarTarea($tarea->fresh(), $this->jefeDeTaller(), [
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 3]],
        ]);

        $this->assertDatabaseHas('solicitudes_insumo_ot', [
            'detalle_ot_id' => $tarea->id,
            'inventario_id' => $item->id,
            'estado' => 'pendiente',
            'motivo_rechazo' => null,
        ]);
        $this->assertDatabaseHas('ot_eventos', ['ot_id' => $ot->id, 'tipo' => 'insumo_reactivado']);
    }

    public function test_no_se_puede_quitar_una_linea_de_insumo_ya_entregada(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tecnico = Tecnico::factory()->conSueldo()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 100]);

        $tarea = app(OrdenTrabajoService::class)->agregarTarea($ot, $this->jefeDeTaller(), [
            'descripcion' => 'Tarea',
            'tecnico_id' => $tecnico->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 3]],
        ]);

        SolicitudInsumoOt::where('detalle_ot_id', $tarea->id)->update(['estado' => 'entregada']);

        try {
            app(OrdenTrabajoService::class)->actualizarTarea($tarea->fresh(), $this->jefeDeTaller(), ['insumos' => []]);
            $this->fail('Debía bloquear quitar una línea ya entregada.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('devolución en Inventario', $e->getMessage());
        }

        $this->assertSame(1, $tarea->fresh()->insumos()->count());
        $this->assertSame('entregada', SolicitudInsumoOt::where('detalle_ot_id', $tarea->id)->value('estado'));
    }

    public function test_no_se_puede_cambiar_la_cantidad_de_una_linea_ya_entregada(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tecnico = Tecnico::factory()->conSueldo()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 100]);

        $tarea = app(OrdenTrabajoService::class)->agregarTarea($ot, $this->jefeDeTaller(), [
            'descripcion' => 'Tarea',
            'tecnico_id' => $tecnico->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 3]],
        ]);

        SolicitudInsumoOt::where('detalle_ot_id', $tarea->id)->update(['estado' => 'entregada']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        try {
            app(OrdenTrabajoService::class)->actualizarTarea($tarea->fresh(), $this->jefeDeTaller(), [
                'insumos' => [['inventario_id' => $item->id, 'cantidad' => 9]],
            ]);
        } finally {
            $this->assertEquals(3, (float) $tarea->fresh()->insumos()->value('cantidad'));
        }
    }

    public function test_el_costeo_ignora_las_lineas_rechazadas_o_canceladas(): void
    {
        $ot = $this->crearOt(tareas: 1, datos: ['valor_proyecto' => 1_000_000]);
        $tecnico = Tecnico::factory()->conSueldo(3_000_000)->create();
        $usado = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 100, 'costo_unitario' => 10_000]);
        $rechazado = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 100, 'costo_unitario' => 50_000]);

        $tarea = app(OrdenTrabajoService::class)->agregarTarea($ot, $this->jefeDeTaller(), [
            'descripcion' => 'Tarea',
            'tecnico_id' => $tecnico->id,
            'insumos' => [
                ['inventario_id' => $usado->id, 'cantidad' => 3],
                ['inventario_id' => $rechazado->id, 'cantidad' => 1],
            ],
        ]);

        SolicitudInsumoOt::where('detalle_ot_id', $tarea->id)
            ->where('inventario_id', $rechazado->id)
            ->update(['estado' => 'rechazada', 'motivo_rechazo' => 'Se pidió de más']);

        $costeo = app(CosteoOtService::class)->calcular($ot->fresh());

        // Solo cuenta 3 × 10.000; el ítem rechazado (50.000) queda fuera.
        $this->assertEqualsWithDelta(30_000, $costeo['repuestos'], 0.01);
    }
}
