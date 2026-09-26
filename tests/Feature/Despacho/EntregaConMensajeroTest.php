<?php

namespace Tests\Feature\Despacho;

use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\SolicitudDespacho;
use App\Services\Inventario\DespachoService;
use App\Services\Inventario\FirmaFisicaPendienteService;
use App\Services\Inventario\MovimientoService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Envío con mensajero: el almacenista firma solo "quien entrega", el stock
 * sale de una vez, y la solicitud queda `despachada` hasta que se adjunta la
 * foto del papel firmado por el cliente.
 */
class EntregaConMensajeroTest extends TestCase
{
    use DespachoTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Mail::fake();
        Storage::fake('public');
    }

    private function solicitudRemisionada(): array
    {
        $despachos = app(DespachoService::class);
        $movimientos = app(MovimientoService::class);
        $almacen = $this->almacenista();

        $cliente = Cliente::factory()->create(['correo' => 'cliente@servireparar.com']);
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 0, 'costo_unitario' => 0]);
        $movimientos->entrada($item, 20, $almacen, costoUnitario: 100);

        $solicitud = $despachos->crear($this->vendedor(), $cliente->id, null, [
            ['origen' => 'inventario', 'inventario_id' => $item->id, 'descripcion' => '', 'cantidad' => '5', 'proveedor_externo' => '', 'costo_compra_externa' => ''],
        ]);
        $despachos->recibir($solicitud, $almacen);
        $despachos->generarRemision($solicitud, $almacen);

        return [$despachos, $almacen, $solicitud, $item];
    }

    public function test_confirmar_salida_con_mensajero_descuenta_stock_y_deja_pendiente_la_firma_fisica(): void
    {
        [$despachos, $almacen, $solicitud, $item] = $this->solicitudRemisionada();

        $despachos->confirmarSalidaMensajero($solicitud, $almacen, $this->firmaDummy(), 'Herwin Menco', 'Pedro Mensajero', 'Se entrega para firma del cliente.');

        $solicitud->refresh();
        $this->assertSame('despachada', $solicitud->estado);
        $this->assertSame('Pedro Mensajero', $solicitud->mensajero_nombre);
        $this->assertNotNull($solicitud->despachada_en);
        $this->assertTrue($solicitud->puedeAnularse() === false);

        // El stock ya salió, aunque el cliente todavía no ha firmado nada.
        $this->assertEquals(15, $item->fresh()->stock_actual);
        $movs = MovimientoInventario::where('origen', 'despacho')->get();
        $this->assertCount(1, $movs);

        $remision = $solicitud->remision->fresh();
        $this->assertNotEmpty($remision->firma_entrega);
        $this->assertNull($remision->firma);
        $this->assertNull($remision->recibido_por_nombre);

        Mail::assertNothingSent();
    }

    public function test_confirmar_salida_con_mensajero_exige_firma_de_quien_entrega(): void
    {
        [$despachos, $almacen, $solicitud] = $this->solicitudRemisionada();

        try {
            $despachos->confirmarSalidaMensajero($solicitud, $almacen, '');
            $this->fail('Se esperaba ValidationException por falta de firma de quien entrega.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('firmaEntrega', $e->errors());
        }

        $this->assertSame('remisionada', $solicitud->fresh()->estado);
    }

    public function test_registrar_firma_fisica_cierra_la_entrega_sin_volver_a_mover_stock(): void
    {
        [$despachos, $almacen, $solicitud, $item] = $this->solicitudRemisionada();
        $despachos->confirmarSalidaMensajero($solicitud, $almacen, $this->firmaDummy(), null, 'Pedro Mensajero');

        $foto = UploadedFile::fake()->image('firma-fisica.jpg')->store('remisiones', 'public');

        $correoEnviado = $despachos->confirmarFirmaFisica($solicitud, $almacen, 'Juan Cliente', '12345678', $foto);

        $this->assertTrue($correoEnviado);
        $solicitud->refresh();
        $this->assertSame('entregada', $solicitud->estado);
        $this->assertNotNull($solicitud->entregada_en);

        $remision = $solicitud->remision->fresh();
        $this->assertSame('Juan Cliente', $remision->recibido_por_nombre);
        $this->assertSame('12345678', $remision->recibido_por_documento);
        $this->assertSame($foto, $remision->firma_fisica_foto);
        $this->assertNotNull($remision->firma_fisica_recibida_en);

        // El stock no se mueve de nuevo al cerrar con la firma física.
        $this->assertEquals(15, $item->fresh()->stock_actual);
        $this->assertCount(1, MovimientoInventario::where('origen', 'despacho')->get());
    }

    public function test_no_se_puede_registrar_firma_fisica_sin_haber_despachado_con_mensajero(): void
    {
        [$despachos, $almacen, $solicitud] = $this->solicitudRemisionada();

        $this->expectException(ValidationException::class);
        $despachos->confirmarFirmaFisica($solicitud, $almacen, 'Juan Cliente', '123', 'remisiones/foto.jpg');
    }

    public function test_una_solicitud_despachada_con_mensajero_ya_no_se_puede_anular(): void
    {
        [$despachos, $almacen, $solicitud] = $this->solicitudRemisionada();
        $despachos->confirmarSalidaMensajero($solicitud, $almacen, $this->firmaDummy());

        $this->assertFalse($solicitud->fresh()->puedeAnularse());
    }

    public function test_revisar_firmas_pendientes_avisa_solo_una_vez_por_solicitud(): void
    {
        Notification::fake();

        [$despachos, $almacen, $solicitud] = $this->solicitudRemisionada();
        $despachos->confirmarSalidaMensajero($solicitud, $almacen, $this->firmaDummy(), null, 'Pedro Mensajero');

        SolicitudDespacho::whereKey($solicitud->id)->update([
            'despachada_en' => now()->subDays(5),
        ]);

        $service = app(FirmaFisicaPendienteService::class);

        $this->assertSame(1, $service->revisar());
        $this->assertNotNull($solicitud->fresh()->alertado_firma_pendiente_en);

        // Sin --reenviar, la segunda corrida no vuelve a avisar.
        $this->assertSame(0, $service->revisar());

        Notification::assertSentTimes(\App\Notifications\OtNotificacion::class, 2);
    }
}
