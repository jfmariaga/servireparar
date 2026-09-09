<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Models\Inventario;
use App\Models\SolicitudInsumoOt;
use App\Models\Tecnico;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 11 / D1 — el stock se reserva al guardar la tarea y no se puede
 * comprometer más de lo realmente disponible (stock − comprometido).
 */
class ReservaStockTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    private function tareaConInsumo($ot, Inventario $item, float $cantidad): void
    {
        app(OrdenTrabajoService::class)->agregarTarea($ot, $this->jefeDeTaller(), [
            'descripcion' => 'Tarea',
            'tecnico_id' => Tecnico::factory()->conSueldo()->create()->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => $cantidad]],
        ]);
    }

    public function test_la_reserva_descuenta_el_disponible(): void
    {
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 10]);
        $ot = $this->crearOt(tareas: 1);

        $this->tareaConInsumo($ot, $item, 4);

        $this->assertEquals(4, $item->fresh()->comprometido());
        $this->assertEquals(6, $item->fresh()->disponible());
        $this->assertEquals(10, (float) $item->fresh()->stock_actual, 'La reserva NO toca el stock físico');
    }

    public function test_una_segunda_ot_no_puede_comprometer_sobre_el_disponible(): void
    {
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 5]);
        $ot1 = $this->crearOt(tareas: 1);
        $ot2 = $this->crearOt(tareas: 1);

        $this->tareaConInsumo($ot1, $item, 4);

        try {
            $this->tareaConInsumo($ot2, $item, 4);
            $this->fail('Debía bloquear: solo queda 1 disponible.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('disponibles', $e->getMessage());
        }

        $this->assertSame(1, SolicitudInsumoOt::where('ot_id', $ot1->id)->count());
        $this->assertSame(0, SolicitudInsumoOt::where('ot_id', $ot2->id)->count());
    }

    public function test_se_puede_reservar_justo_hasta_el_disponible(): void
    {
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 5]);
        $ot1 = $this->crearOt(tareas: 1);
        $ot2 = $this->crearOt(tareas: 1);

        $this->tareaConInsumo($ot1, $item, 3);
        $this->tareaConInsumo($ot2, $item, 2);

        $this->assertEquals(0, $item->fresh()->disponible());
        $this->assertSame(2, SolicitudInsumoOt::comprometidas()->where('inventario_id', $item->id)->count());
    }

    public function test_mantener_o_reducir_una_reserva_existente_nunca_se_bloquea(): void
    {
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 5]);
        $ot = $this->crearOt(tareas: 1);
        $tarea = app(OrdenTrabajoService::class)->agregarTarea($ot, $this->jefeDeTaller(), [
            'descripcion' => 'Tarea',
            'tecnico_id' => Tecnico::factory()->conSueldo()->create()->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 5]],
        ]);

        // Re-guardar igual y luego reducir: sin excepción.
        app(OrdenTrabajoService::class)->actualizarTarea($tarea->fresh(), $this->jefeDeTaller(), [
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 5]],
        ]);
        app(OrdenTrabajoService::class)->actualizarTarea($tarea->fresh(), $this->jefeDeTaller(), [
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 2]],
        ]);

        $this->assertEquals(2, (float) $tarea->fresh()->insumos()->value('cantidad'));
        $this->assertEquals(3, $item->fresh()->disponible());
    }

    public function test_entregar_no_cambia_el_disponible(): void
    {
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 10, 'costo_unitario' => 1000]);
        $item->movimientos()->create([
            'tipo_mov' => 'entrada', 'cantidad' => 10, 'cantidad_disponible' => 10, 'costo_unitario' => 1000,
            'fecha' => now(), 'usuario_id' => $this->administrador()->id, 'origen' => 'entrada_proveedor',
        ]);
        $ot = $this->crearOt(tareas: 1);
        $this->tareaConInsumo($ot, $item, 4);

        $this->assertEquals(6, $item->fresh()->disponible());

        $solicitud = SolicitudInsumoOt::where('ot_id', $ot->id)->firstOrFail();
        app(\App\Services\OrdenTrabajo\AtencionInsumoOtService::class)->entregar($solicitud, $this->administrador());

        $item->refresh();
        $this->assertEquals(6, (float) $item->stock_actual);
        $this->assertEquals(0, $item->comprometido());
        $this->assertEquals(6, $item->disponible());
    }
}
