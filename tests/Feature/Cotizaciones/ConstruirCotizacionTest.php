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
        $this->assertEquals(160000, $cotizacion->fresh()->total);
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
        $this->assertEquals(50000, $cotizacion->fresh()->total);
    }
}
