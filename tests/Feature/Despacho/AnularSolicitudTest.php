<?php

namespace Tests\Feature\Despacho;

use App\Models\Cliente;
use App\Models\Inventario;
use App\Services\Inventario\DespachoService;
use App\Services\Inventario\MovimientoService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AnularSolicitudTest extends TestCase
{
    use DespachoTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Mail::fake();
    }

    private function solicitudConStock(): array
    {
        $cliente = Cliente::factory()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 0, 'costo_unitario' => 0]);
        app(MovimientoService::class)->entrada($item, 50, $this->almacenista(), costoUnitario: 100);

        $solicitud = app(DespachoService::class)->crear($this->vendedor(), $cliente->id, null, [
            ['origen' => 'inventario', 'inventario_id' => $item->id, 'descripcion' => '', 'cantidad' => '5', 'proveedor_externo' => '', 'costo_compra_externa' => ''],
        ]);

        return [$solicitud, $item];
    }

    public function test_se_puede_anular_en_solicitada_recibida_y_remisionada_sin_tocar_stock(): void
    {
        $despachos = app(DespachoService::class);
        $almacen = $this->almacenista();

        foreach (['solicitada', 'recibida', 'remisionada'] as $estadoObjetivo) {
            [$solicitud, $item] = $this->solicitudConStock();

            if ($estadoObjetivo !== 'solicitada') {
                $despachos->recibir($solicitud, $almacen);
            }
            if ($estadoObjetivo === 'remisionada') {
                $despachos->generarRemision($solicitud, $almacen);
            }

            $despachos->anular($solicitud, $this->vendedor(), 'Cliente desistió');

            $solicitud->refresh();
            $this->assertSame('anulada', $solicitud->estado);
            $this->assertSame('Cliente desistió', $solicitud->motivo_anulacion);
            $this->assertEquals(50, $item->fresh()->stock_actual);
        }
    }

    public function test_no_se_puede_anular_una_solicitud_entregada(): void
    {
        [$solicitud] = $this->solicitudConStock();
        $despachos = app(DespachoService::class);
        $almacen = $this->almacenista();

        $despachos->recibir($solicitud, $almacen);
        $despachos->generarRemision($solicitud, $almacen);
        $despachos->confirmarEntrega($solicitud, $almacen, 'Cliente', '123', $this->firmaDummy(), null, null, $this->firmaDummy());

        $this->expectException(ValidationException::class);
        $despachos->anular($solicitud, $almacen);
    }
}
