<?php

namespace Tests\Feature\Cotizaciones;

use App\Contracts\ProveedorCorreoSaliente;
use App\Models\Cotizacion;
use App\Models\Inventario;
use App\Models\Servicio;
use App\Services\Cotizaciones\CotizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec 006, US2 (FR-002): construir una cotización con ítems de la maestra de
 * servicios y de inventario, calculando el total automáticamente.
 */
class ConstruirCotizacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardar_items_calcula_el_total_automaticamente(): void
    {
        $cotizacion = Cotizacion::factory()->create();
        $servicio = Servicio::factory()->create(['costo_unitario' => 50000]);
        $insumo = Inventario::factory()->create(['costo_unitario' => 20000]);

        $servicio = app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'servicio', 'servicio_id' => $servicio->id, 'cantidad' => 2],
            ['tipo_item' => 'insumo', 'inventario_id' => $insumo->id, 'cantidad' => 3],
        ]);

        $this->assertSame(2, $servicio->detalles->count());
        $this->assertEquals(160000, $cotizacion->fresh()->subtotal);
        $this->assertEquals(190400, $cotizacion->fresh()->total);
    }

    public function test_guardar_items_reemplaza_los_anteriores(): void
    {
        $cotizacion = Cotizacion::factory()->create();
        $servicio = Servicio::factory()->create(['costo_unitario' => 10000]);

        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'servicio', 'servicio_id' => $servicio->id, 'cantidad' => 1],
        ]);
        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'servicio', 'servicio_id' => $servicio->id, 'cantidad' => 5],
        ]);

        $this->assertSame(1, $cotizacion->fresh()->detalles->count());
        $this->assertEquals(59500, $cotizacion->fresh()->total);
    }

    public function test_precio_de_insumo_es_editable_solo_en_la_cotizacion(): void
    {
        $cotizacion = Cotizacion::factory()->create();
        $insumo = Inventario::factory()->create(['costo_unitario' => 20000]);

        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'insumo', 'inventario_id' => $insumo->id, 'origen' => 'inventario', 'costo_unitario' => 15000, 'cantidad' => 2],
        ]);

        $detalle = $cotizacion->fresh()->detalles->first();
        $this->assertEquals(15000, $detalle->costo_unitario);
        $this->assertEquals(30000, $detalle->valor_total);
        $this->assertEquals(20000, $insumo->fresh()->costo_unitario);
    }

    public function test_insumo_de_compra_externa_no_descuenta_inventario_al_guardar(): void
    {
        $cotizacion = Cotizacion::factory()->create();
        $insumo = Inventario::factory()->create(['costo_unitario' => 20000, 'stock_actual' => 10]);

        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'insumo', 'inventario_id' => $insumo->id, 'origen' => 'compra_externa', 'costo_unitario' => 25000, 'cantidad' => 3],
        ]);

        $detalle = $cotizacion->fresh()->detalles->first();
        $this->assertTrue($detalle->esCompraExterna());
        $this->assertEquals(10, $insumo->fresh()->stock_actual);
    }
}
