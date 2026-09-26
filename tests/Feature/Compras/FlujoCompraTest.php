<?php

namespace Tests\Feature\Compras;

use App\Models\Inventario;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\Compras\CompraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Spec 006, US4: solicitud de compra a proveedor con flujo de 4 estados
 * (recepción → cotización → aprobación → facturación), cada transición con
 * fecha y responsable.
 */
class FlujoCompraTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_compra_calcula_el_total_y_queda_en_recepcion(): void
    {
        $proveedor = Proveedor::factory()->create();
        $creador = User::factory()->create();
        $item = Inventario::factory()->create();

        $compra = app(CompraService::class)->crear($proveedor, $creador, [
            ['inventario_id' => $item->id, 'cantidad' => 3, 'costo_unitario' => 15000],
        ]);

        $this->assertSame('recepcion', $compra->estado);
        $this->assertEquals(45000, $compra->total);
        $this->assertSame($creador->id, $compra->creado_por);
    }

    public function test_avanza_por_los_4_estados_con_fecha_registrada(): void
    {
        $proveedor = Proveedor::factory()->create();
        $creador = User::factory()->create();
        $item = Inventario::factory()->create();

        $compra = app(CompraService::class)->crear($proveedor, $creador, [
            ['inventario_id' => $item->id, 'cantidad' => 1, 'costo_unitario' => 10000],
        ]);

        app(CompraService::class)->marcarCotizacion($compra);
        $this->assertSame('cotizacion', $compra->fresh()->estado);
        $this->assertNotNull($compra->fresh()->cotizada_en);

        app(CompraService::class)->aprobar($compra->fresh());
        $this->assertSame('aprobacion', $compra->fresh()->estado);
        $this->assertNotNull($compra->fresh()->aprobada_en);

        app(CompraService::class)->facturar($compra->fresh());
        $this->assertSame('facturada', $compra->fresh()->estado);
        $this->assertNotNull($compra->fresh()->facturada_en);
    }

    public function test_no_permite_saltar_estados(): void
    {
        $proveedor = Proveedor::factory()->create();
        $creador = User::factory()->create();
        $item = Inventario::factory()->create();

        $compra = app(CompraService::class)->crear($proveedor, $creador, [
            ['inventario_id' => $item->id, 'cantidad' => 1, 'costo_unitario' => 10000],
        ]);

        $this->expectException(ValidationException::class);

        app(CompraService::class)->aprobar($compra);
    }

    public function test_crear_sin_items_falla(): void
    {
        $proveedor = Proveedor::factory()->create();
        $creador = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(CompraService::class)->crear($proveedor, $creador, []);
    }
}
