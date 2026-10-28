<?php

namespace Tests\Feature\Cotizaciones;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Inventario;
use App\Models\Servicio;
use App\Services\Cotizaciones\CotizacionService;
use App\Services\Inventario\DespachoService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Despacho\DespachoTestHelpers;
use Tests\TestCase;

/**
 * "Solicitar despacho" en una cotización aceptada (enlace entre Cotizaciones
 * y el canal de despacho, spec 003): las líneas de insumo "sale del almacén"
 * quedan como línea de inventario (descuenta stock al confirmarse la
 * entrega del despacho, no antes); las de "compra externa" no afectan
 * inventario.
 */
class SolicitarDespachoCotizacionTest extends TestCase
{
    use RefreshDatabase;
    use DespachoTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_genera_la_solicitud_de_despacho_con_las_lineas_de_insumo(): void
    {
        $actor = $this->vendedor();
        $cliente = Cliente::factory()->create();
        $cotizacion = Cotizacion::factory()->create(['estado' => 'aceptada', 'cliente_id' => $cliente->id]);
        $servicio = Servicio::factory()->create();
        $delAlmacen = Inventario::factory()->create(['costo_unitario' => 10000, 'stock_actual' => 50]);
        $compraExterna = Inventario::factory()->create(['costo_unitario' => 5000]);

        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'servicio', 'servicio_id' => $servicio->id, 'cantidad' => 1],
            ['tipo_item' => 'insumo', 'inventario_id' => $delAlmacen->id, 'origen' => 'inventario', 'cantidad' => 2],
            ['tipo_item' => 'insumo', 'inventario_id' => $compraExterna->id, 'origen' => 'compra_externa', 'costo_unitario' => 7000, 'cantidad' => 1],
        ]);

        $solicitud = app(CotizacionService::class)->solicitarDespacho($cotizacion->fresh(), $actor, app(DespachoService::class));

        $this->assertSame($cliente->id, $solicitud->cliente_id);
        $this->assertSame($cotizacion->id, $solicitud->cotizacion_id);
        $this->assertSame(2, $solicitud->detalles->count());
        $this->assertSame(1, $solicitud->detallesInventario()->count());
        $this->assertSame(1, $solicitud->detallesCompraExterna()->count());

        // No se descuenta stock al generar la solicitud, solo al entregarla.
        $this->assertEquals(50, $delAlmacen->fresh()->stock_actual);

        $this->assertNotNull($cotizacion->fresh()->solicitudDespacho);
    }

    public function test_no_permite_solicitar_despacho_dos_veces(): void
    {
        $actor = $this->vendedor();
        $cliente = Cliente::factory()->create();
        $cotizacion = Cotizacion::factory()->create(['estado' => 'aceptada', 'cliente_id' => $cliente->id]);
        $insumo = Inventario::factory()->create();

        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'insumo', 'inventario_id' => $insumo->id, 'origen' => 'inventario', 'cantidad' => 1],
        ]);

        app(CotizacionService::class)->solicitarDespacho($cotizacion->fresh(), $actor, app(DespachoService::class));

        $this->expectException(ValidationException::class);
        app(CotizacionService::class)->solicitarDespacho($cotizacion->fresh(), $actor, app(DespachoService::class));
    }

    public function test_no_permite_solicitar_despacho_sin_insumos(): void
    {
        $actor = $this->vendedor();
        $cliente = Cliente::factory()->create();
        $cotizacion = Cotizacion::factory()->create(['estado' => 'aceptada', 'cliente_id' => $cliente->id]);
        $servicio = Servicio::factory()->create();

        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'servicio', 'servicio_id' => $servicio->id, 'cantidad' => 1],
        ]);

        $this->expectException(ValidationException::class);
        app(CotizacionService::class)->solicitarDespacho($cotizacion->fresh(), $actor, app(DespachoService::class));
    }

    public function test_solo_una_cotizacion_aceptada_puede_solicitar_despacho(): void
    {
        $actor = $this->vendedor();
        $cotizacion = Cotizacion::factory()->create(['estado' => 'en_revision']);

        $this->expectException(ValidationException::class);
        app(CotizacionService::class)->solicitarDespacho($cotizacion, $actor, app(DespachoService::class));
    }

    /**
     * Cuando el almacén confirma la entrega del despacho generado desde una
     * cotización, la cotización pasa a "entregada" sola: al usuario que
     * cotizó solo le queda marcarla como facturada.
     */
    public function test_entregar_el_despacho_marca_la_cotizacion_como_entregada(): void
    {
        $vendedor = $this->vendedor();
        $almacen = $this->almacenista();
        $cliente = Cliente::factory()->create(['correo' => null]);
        $cotizacion = Cotizacion::factory()->create(['estado' => 'aceptada', 'cliente_id' => $cliente->id]);
        $insumo = Inventario::factory()->create(['costo_unitario' => 10000, 'stock_actual' => 50]);

        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'insumo', 'inventario_id' => $insumo->id, 'origen' => 'inventario', 'cantidad' => 2],
        ]);

        $despachos = app(DespachoService::class);
        $solicitud = app(CotizacionService::class)->solicitarDespacho($cotizacion->fresh(), $vendedor, $despachos);

        $despachos->recibir($solicitud, $almacen);
        $despachos->generarRemision($solicitud, $almacen);
        $despachos->confirmarEntrega($solicitud, $almacen, 'Juan Cliente', '12345678', $this->firmaDummy(), null, null, $this->firmaDummy());

        $cotizacion->refresh();
        $this->assertSame('entregada', $cotizacion->estado);
        $this->assertTrue(
            $cotizacion->mensajes()->where('contenido', 'like', '%entregada automáticamente%')->exists()
        );
    }

    /**
     * La fecha prometida de la cotización (KPI OTIF) se copia a la solicitud
     * de despacho que genera, para que el cálculo de OTIF pueda usarla.
     */
    public function test_solicitar_despacho_copia_la_fecha_prometida_de_la_cotizacion(): void
    {
        $actor = $this->vendedor();
        $cliente = Cliente::factory()->create();
        $cotizacion = Cotizacion::factory()->create([
            'estado' => 'aceptada',
            'cliente_id' => $cliente->id,
            'fecha_promesa_entrega' => '2026-11-05',
        ]);
        $insumo = Inventario::factory()->create();

        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'insumo', 'inventario_id' => $insumo->id, 'origen' => 'inventario', 'cantidad' => 1],
        ]);

        $solicitud = app(CotizacionService::class)->solicitarDespacho($cotizacion->fresh(), $actor, app(DespachoService::class));

        $this->assertSame('2026-11-05', $solicitud->fresh()->fecha_promesa_entrega->format('Y-m-d'));
    }

    public function test_solicitar_despacho_sin_fecha_prometida_en_la_cotizacion_no_falla(): void
    {
        $actor = $this->vendedor();
        $cliente = Cliente::factory()->create();
        $cotizacion = Cotizacion::factory()->create([
            'estado' => 'aceptada',
            'cliente_id' => $cliente->id,
            'fecha_promesa_entrega' => null,
        ]);
        $insumo = Inventario::factory()->create();

        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'insumo', 'inventario_id' => $insumo->id, 'origen' => 'inventario', 'cantidad' => 1],
        ]);

        $solicitud = app(CotizacionService::class)->solicitarDespacho($cotizacion->fresh(), $actor, app(DespachoService::class));

        $this->assertNull($solicitud->fresh()->fecha_promesa_entrega);
    }

    public function test_si_la_cotizacion_ya_estaba_entregada_a_mano_no_falla_al_sincronizar(): void
    {
        $vendedor = $this->vendedor();
        $almacen = $this->almacenista();
        $cliente = Cliente::factory()->create(['correo' => null]);
        $cotizacion = Cotizacion::factory()->create(['estado' => 'aceptada', 'cliente_id' => $cliente->id]);
        $insumo = Inventario::factory()->create(['costo_unitario' => 10000, 'stock_actual' => 50]);

        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'insumo', 'inventario_id' => $insumo->id, 'origen' => 'inventario', 'cantidad' => 2],
        ]);

        $despachos = app(DespachoService::class);
        $solicitud = app(CotizacionService::class)->solicitarDespacho($cotizacion->fresh(), $vendedor, $despachos);
        $despachos->recibir($solicitud, $almacen);
        $despachos->generarRemision($solicitud, $almacen);

        // El usuario ya la había marcado entregada a mano antes de que el almacén confirmara.
        app(CotizacionService::class)->marcarEntregada($cotizacion->fresh());

        $despachos->confirmarEntrega($solicitud, $almacen, 'Juan Cliente', '12345678', $this->firmaDummy(), null, null, $this->firmaDummy());

        $this->assertSame('entregada', $solicitud->fresh()->estado);
        $this->assertSame('entregada', $cotizacion->fresh()->estado);
    }
}
