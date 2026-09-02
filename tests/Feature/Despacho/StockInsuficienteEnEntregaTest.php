<?php

namespace Tests\Feature\Despacho;

use App\Exceptions\StockInsuficienteException;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Services\Inventario\DespachoService;
use App\Services\Inventario\MovimientoService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StockInsuficienteEnEntregaTest extends TestCase
{
    use DespachoTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Mail::fake();
    }

    public function test_confirmar_entrega_se_bloquea_si_una_linea_no_tiene_stock_y_no_descuenta_el_resto(): void
    {
        $despachos = app(DespachoService::class);
        $movimientos = app(MovimientoService::class);

        $cliente = Cliente::factory()->create();
        $conStock = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 0, 'costo_unitario' => 0]);
        $sinStock = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 0, 'costo_unitario' => 0]);
        $movimientos->entrada($conStock, 100, $this->almacenista(), costoUnitario: 100);
        $movimientos->entrada($sinStock, 1, $this->almacenista(), costoUnitario: 100);

        $solicitud = $despachos->crear($this->vendedor(), $cliente->id, null, [
            ['origen' => 'inventario', 'inventario_id' => $conStock->id, 'descripcion' => '', 'cantidad' => '10', 'proveedor_externo' => '', 'costo_compra_externa' => ''],
            ['origen' => 'inventario', 'inventario_id' => $sinStock->id, 'descripcion' => '', 'cantidad' => '50', 'proveedor_externo' => '', 'costo_compra_externa' => ''],
        ]);

        $almacen = $this->almacenista();
        $despachos->recibir($solicitud, $almacen);
        $despachos->generarRemision($solicitud, $almacen);

        try {
            $despachos->confirmarEntrega($solicitud, $almacen, 'Cliente', '999', $this->firmaDummy(), null, null, $this->firmaDummy());
            $this->fail('Se esperaba StockInsuficienteException.');
        } catch (StockInsuficienteException $e) {
            // esperado
        }

        // Rollback total: ningún movimiento, stock intacto, solicitud sigue remisionada.
        $this->assertSame(0, MovimientoInventario::where('origen', 'despacho')->count());
        $this->assertEquals(100, $conStock->fresh()->stock_actual);
        $this->assertEquals(1, $sinStock->fresh()->stock_actual);
        $this->assertSame('remisionada', $solicitud->fresh()->estado);
    }
}
