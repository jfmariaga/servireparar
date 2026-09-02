<?php

namespace Tests\Feature\Despacho;

use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\RemisionEntrega;
use App\Services\Inventario\DespachoService;
use App\Services\Inventario\MovimientoService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RemisionPdfTest extends TestCase
{
    use DespachoTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Mail::fake();
    }

    public function test_remision_tiene_consecutivo_unico_y_devuelve_pdf(): void
    {
        $despachos = app(DespachoService::class);
        $movimientos = app(MovimientoService::class);
        $almacen = $this->almacenista();

        $remisiones = [];
        foreach (range(1, 2) as $n) {
            $cliente = Cliente::factory()->create();
            $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 0, 'costo_unitario' => 0]);
            $movimientos->entrada($item, 20, $almacen, costoUnitario: 100);

            $solicitud = $despachos->crear($this->vendedor(), $cliente->id, null, [
                ['origen' => 'inventario', 'inventario_id' => $item->id, 'descripcion' => '', 'cantidad' => '2', 'proveedor_externo' => '', 'costo_compra_externa' => ''],
                ['origen' => 'compra_externa', 'inventario_id' => null, 'descripcion' => 'Item externo', 'cantidad' => '1', 'proveedor_externo' => 'Prov', 'costo_compra_externa' => '1000'],
            ]);
            $despachos->recibir($solicitud, $almacen);
            $remisiones[] = $despachos->generarRemision($solicitud, $almacen)->numero;
            $despachos->confirmarEntrega($solicitud, $almacen, 'Cliente '.$n, (string) $n, $this->firmaDummy(), null, null, $this->firmaDummy());

            $response = $this->actingAs($almacen)->get(route('despachos.remision', $solicitud));
            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('content-type'));
        }

        $this->assertSame(['REM-00001', 'REM-00002'], $remisiones);
        $this->assertSame(2, RemisionEntrega::whereNotNull('firma')->count());
    }

    public function test_el_pdf_no_expone_la_trazabilidad_interna_de_compra_externa(): void
    {
        $despachos = app(DespachoService::class);
        $movimientos = app(MovimientoService::class);
        $almacen = $this->almacenista();

        $cliente = Cliente::factory()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 0, 'costo_unitario' => 0]);
        $movimientos->entrada($item, 20, $almacen, costoUnitario: 100);

        $solicitud = $despachos->crear($this->vendedor(), $cliente->id, null, [
            ['origen' => 'inventario', 'inventario_id' => $item->id, 'descripcion' => '', 'cantidad' => '2', 'proveedor_externo' => '', 'costo_compra_externa' => ''],
            ['origen' => 'compra_externa', 'inventario_id' => null, 'descripcion' => 'Ventilador extractor', 'cantidad' => '1', 'proveedor_externo' => 'Ferretería X', 'costo_compra_externa' => '80000'],
        ]);
        $despachos->recibir($solicitud, $almacen);
        $despachos->generarRemision($solicitud, $almacen);
        $despachos->confirmarEntrega($solicitud, $almacen, 'Maikol Gonzalez', '1043278', $this->firmaDummy(), null, null, $this->firmaDummy());

        $html = view('pdf.remision-entrega', ['solicitud' => $solicitud->fresh(['cliente', 'vendedor', 'detalles.inventario', 'remision.generadaPor'])])->render();

        $this->assertStringContainsString('Despachamos a ustedes los siguientes artículos', $html);
        $this->assertStringContainsString('REMISIÓN BAQ', $html);
        $this->assertStringContainsString('Ventilador extractor', $html);
        $this->assertStringNotContainsString('No disponible en almacén', $html);
        $this->assertStringNotContainsString('Ferretería X', $html);
        $this->assertStringNotContainsString('Compra externa', $html);
    }
}
