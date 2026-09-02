<?php

namespace Tests\Feature\Despacho;

use App\Mail\RemisionEntregada;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Services\Inventario\DespachoService;
use App\Services\Inventario\MovimientoService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FlujoEntregaFirmadaTest extends TestCase
{
    use DespachoTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Mail::fake();
    }

    public function test_recibir_remisionar_firmar_y_entregar_genera_salida_despacho_con_fifo(): void
    {
        $despachos = app(DespachoService::class);
        $movimientos = app(MovimientoService::class);

        $cliente = Cliente::factory()->create(['correo' => 'compras@menzies.test']);
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 0, 'costo_unitario' => 0]);

        // Dos lotes de entrada a costos distintos → la salida de 15 cruza ambos.
        $movimientos->entrada($item, 10, $this->almacenista(), costoUnitario: 100);
        $movimientos->entrada($item, 10, $this->almacenista(), costoUnitario: 200);
        $item->refresh();
        $this->assertEquals(20, $item->stock_actual);

        $solicitud = $despachos->crear($this->vendedor(), $cliente->id, null, [
            ['origen' => 'inventario', 'inventario_id' => $item->id, 'descripcion' => '', 'cantidad' => '15', 'proveedor_externo' => '', 'costo_compra_externa' => ''],
            ['origen' => 'compra_externa', 'inventario_id' => null, 'descripcion' => 'Cinta especial', 'cantidad' => '1', 'proveedor_externo' => 'Prov X', 'costo_compra_externa' => '5000'],
        ]);

        $almacen = $this->almacenista();
        $despachos->recibir($solicitud, $almacen);
        $this->assertSame('recibida', $solicitud->fresh()->estado);

        $remision = $despachos->generarRemision($solicitud, $almacen);
        $this->assertSame('REM-00001', $remision->numero);
        $this->assertSame('remisionada', $solicitud->fresh()->estado);

        $correoEnviado = $despachos->confirmarEntrega($solicitud, $almacen, 'Juan Cliente', '12345678', $this->firmaDummy(), 'Herwin Menco', 'Se entregan en buen estado al señor Juan.', $this->firmaDummy());

        $this->assertTrue($correoEnviado);
        Mail::assertSent(RemisionEntregada::class, fn ($mail) => $mail->hasTo('compras@menzies.test'));

        $solicitud->refresh();
        $this->assertSame('entregada', $solicitud->estado);
        $this->assertNotNull($solicitud->entregada_en);

        // Solo la línea de inventario generó movimiento.
        $movs = MovimientoInventario::where('origen', 'despacho')->get();
        $this->assertCount(1, $movs);
        $mov = $movs->first();
        $this->assertSame($item->id, $mov->inventario_id);
        $this->assertEquals(15, $mov->cantidad);
        $this->assertSame($remision->numero, $mov->referencia);
        // FIFO: (10 × 100) + (5 × 200) = 2000 / 15 ≈ 133.33
        $this->assertEqualsWithDelta(133.33, (float) $mov->costo_unitario, 0.01);

        $this->assertEquals(5, $item->fresh()->stock_actual);

        $remision->refresh();
        $this->assertSame('Juan Cliente', $remision->recibido_por_nombre);
        $this->assertSame('Herwin Menco', $remision->entregado_por_nombre);
        $this->assertSame('Se entregan en buen estado al señor Juan.', $remision->nota_entrega);
        $this->assertNotEmpty($remision->firma);
        $this->assertNotEmpty($remision->firma_entrega);
        $this->assertNotNull($remision->entregada_en);
        $this->assertNotNull($remision->enviada_al_cliente_en);

        $this->assertNull($solicitud->detallesCompraExterna->first()->movimiento_id);
    }

    public function test_la_entrega_exige_firma_de_quien_entrega_y_de_quien_recibe(): void
    {
        $despachos = app(DespachoService::class);
        $movimientos = app(MovimientoService::class);
        $almacen = $this->almacenista();

        $cliente = Cliente::factory()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 0, 'costo_unitario' => 0]);
        $movimientos->entrada($item, 20, $almacen, costoUnitario: 100);

        $solicitud = $despachos->crear($this->vendedor(), $cliente->id, null, [
            ['origen' => 'inventario', 'inventario_id' => $item->id, 'descripcion' => '', 'cantidad' => '2', 'proveedor_externo' => '', 'costo_compra_externa' => ''],
        ]);
        $despachos->recibir($solicitud, $almacen);
        $despachos->generarRemision($solicitud, $almacen);

        // Falta la firma de quien entrega.
        try {
            $despachos->confirmarEntrega($solicitud, $almacen, 'Cliente', '1', $this->firmaDummy(), null, null, '');
            $this->fail('Se esperaba ValidationException por falta de firma de quien entrega.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('firmaEntrega', $e->errors());
        }

        $this->assertSame('remisionada', $solicitud->fresh()->estado);
    }

    public function test_si_el_cliente_no_tiene_correo_no_se_envia_copia(): void
    {
        $despachos = app(DespachoService::class);
        $movimientos = app(MovimientoService::class);
        $almacen = $this->almacenista();

        $cliente = Cliente::factory()->create(['correo' => null]);
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 0, 'costo_unitario' => 0]);
        $movimientos->entrada($item, 20, $almacen, costoUnitario: 100);

        $solicitud = $despachos->crear($this->vendedor(), $cliente->id, null, [
            ['origen' => 'inventario', 'inventario_id' => $item->id, 'descripcion' => '', 'cantidad' => '2', 'proveedor_externo' => '', 'costo_compra_externa' => ''],
        ]);
        $despachos->recibir($solicitud, $almacen);
        $despachos->generarRemision($solicitud, $almacen);

        $correoEnviado = $despachos->confirmarEntrega($solicitud, $almacen, 'Alguien', '1', $this->firmaDummy(), null, null, $this->firmaDummy());

        $this->assertFalse($correoEnviado);
        Mail::assertNothingSent();
        $this->assertNull($solicitud->fresh()->remision->enviada_al_cliente_en);
        $this->assertSame('entregada', $solicitud->fresh()->estado);
    }
}
