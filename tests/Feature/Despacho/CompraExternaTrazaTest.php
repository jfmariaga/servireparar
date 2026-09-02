<?php

namespace Tests\Feature\Despacho;

use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Services\Inventario\DespachoService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompraExternaTrazaTest extends TestCase
{
    use DespachoTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_linea_de_compra_externa_no_crea_item_ni_lote_ni_movimiento(): void
    {
        $cliente = Cliente::factory()->create();
        $itemsAntes = Inventario::count();

        $solicitud = app(DespachoService::class)->crear($this->vendedor(), $cliente->id, null, [
            ['origen' => 'compra_externa', 'inventario_id' => null, 'descripcion' => 'Rodamiento raro', 'cantidad' => '3', 'proveedor_externo' => 'Importados SAS', 'costo_compra_externa' => '90000'],
        ]);

        $detalle = $solicitud->detalles->first();

        $this->assertSame('compra_externa', $detalle->origen);
        $this->assertNull($detalle->inventario_id);
        $this->assertSame('Importados SAS', $detalle->proveedor_externo);
        $this->assertEquals(90000, $detalle->costo_compra_externa);
        $this->assertSame('No disponible en almacén', $detalle->motivo);

        $this->assertSame($itemsAntes, Inventario::count(), 'La compra externa no debe crear ítems de catálogo.');
        $this->assertSame(0, MovimientoInventario::count(), 'La compra externa no debe generar movimientos de inventario.');
    }
}
