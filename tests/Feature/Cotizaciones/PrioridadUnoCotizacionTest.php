<?php

namespace Tests\Feature\Cotizaciones;

use App\Enums\RolPrioridad;
use App\Mail\CotizacionEnviada;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Cotizaciones\ConsecutivoCotizacionService;
use App\Services\Cotizaciones\CotizacionService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PrioridadUnoCotizacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function administrador(): User
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole(RolPrioridad::Administrador->value);

        return $admin;
    }

    private function cotizacionConItems(array $atributos = []): Cotizacion
    {
        $cliente = Cliente::factory()->create(['correo' => 'cliente@ejemplo.com']);
        $cotizacion = Cotizacion::factory()->create(array_merge(['cliente_id' => $cliente->id], $atributos));
        $servicio = Servicio::factory()->create(['costo_unitario' => 1000]);

        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'servicio', 'servicio_id' => $servicio->id, 'cantidad' => 2],
        ]);

        return $cotizacion->fresh();
    }

    public function test_el_consecutivo_es_independiente_por_sede(): void
    {
        $consecutivo = new ConsecutivoCotizacionService();

        $this->assertSame('CTG-0001', $consecutivo->siguiente('CTG'));

        Cotizacion::factory()->create(['numero' => 'CTG-0001', 'sede' => 'CTG']);
        Cotizacion::factory()->create(['numero' => 'CTG-0002', 'sede' => 'CTG']);

        $this->assertSame('CTG-0003', $consecutivo->siguiente('CTG'));
        $this->assertSame('BAQ-0001', $consecutivo->siguiente('BAQ'));
    }

    public function test_totales_aplican_descuento_de_cabecera_y_luego_iva(): void
    {
        $cotizacion = $this->cotizacionConItems(['descuento_porcentaje' => 10]);

        $this->assertSame('2000.00', $cotizacion->subtotal);
        $this->assertSame('200.00', $cotizacion->descuento_valor);
        $this->assertSame('342.00', $cotizacion->iva_valor);
        $this->assertSame('2142.00', $cotizacion->total);
    }

    public function test_cambiar_sede_en_revision_renumera_la_cotizacion(): void
    {
        $cotizacion = Cotizacion::factory()->create(['numero' => 'BAQ-0007', 'sede' => 'BAQ']);

        $actualizada = app(CotizacionService::class)->guardarCabecera($cotizacion, [
            'sede' => 'MTR',
            'descuento_porcentaje' => 0,
        ]);

        $this->assertSame('MTR', $actualizada->sede);
        $this->assertSame('MTR-0001', $actualizada->numero);
    }

    public function test_guardar_cabecera_persiste_la_fecha_prometida_de_entrega(): void
    {
        $cotizacion = Cotizacion::factory()->create(['sede' => 'BAQ']);

        $actualizada = app(CotizacionService::class)->guardarCabecera($cotizacion, [
            'sede' => 'BAQ',
            'descuento_porcentaje' => 0,
            'fecha_promesa_entrega' => '2026-11-10',
        ]);

        $this->assertSame('2026-11-10', $actualizada->fecha_promesa_entrega->format('Y-m-d'));
    }

    public function test_no_se_puede_cambiar_sede_una_vez_enviada(): void
    {
        $cotizacion = Cotizacion::factory()->create(['estado' => 'cotizada']);

        $this->expectException(ValidationException::class);

        app(CotizacionService::class)->guardarCabecera($cotizacion, [
            'sede' => 'RCH',
            'descuento_porcentaje' => 5,
        ]);
    }

    public function test_aceptacion_manual_desde_cotizada(): void
    {
        $cotizacion = Cotizacion::factory()->create(['estado' => 'cotizada']);

        $aceptada = app(CotizacionService::class)->aceptar($cotizacion, $this->administrador());

        $this->assertSame('aceptada', $aceptada->estado);
        $this->assertTrue($aceptada->mensajes()->where('autor_tipo', 'sistema')->exists());
    }

    public function test_no_se_acepta_una_cotizacion_que_no_fue_enviada(): void
    {
        $cotizacion = Cotizacion::factory()->create(['estado' => 'en_revision']);

        $this->expectException(ValidationException::class);

        app(CotizacionService::class)->aceptar($cotizacion, $this->administrador());
    }

    public function test_rechazo_manual_desde_cotizada(): void
    {
        $cotizacion = Cotizacion::factory()->create(['estado' => 'cotizada']);

        $rechazada = app(CotizacionService::class)->rechazar($cotizacion, $this->administrador());

        $this->assertSame('rechazada', $rechazada->estado);
        $this->assertTrue($rechazada->mensajes()->where('autor_tipo', 'sistema')->exists());
    }

    public function test_no_se_rechaza_una_cotizacion_que_no_fue_enviada(): void
    {
        $cotizacion = Cotizacion::factory()->create(['estado' => 'en_revision']);

        $this->expectException(ValidationException::class);

        app(CotizacionService::class)->rechazar($cotizacion, $this->administrador());
    }

    public function test_reabrir_y_reenviar_conserva_el_historial_del_hilo(): void
    {
        Mail::fake();
        $cotizacion = $this->cotizacionConItems();
        $admin = $this->administrador();

        app(CotizacionService::class)->enviar($cotizacion, $admin);
        $mensajesTrasPrimerEnvio = $cotizacion->fresh()->mensajes()->count();

        app(CotizacionService::class)->reabrir($cotizacion->fresh(), $admin);
        $this->assertSame('en_revision', $cotizacion->fresh()->estado);

        app(CotizacionService::class)->enviar($cotizacion->fresh(), $admin);

        $this->assertSame('cotizada', $cotizacion->fresh()->estado);
        $this->assertGreaterThan($mensajesTrasPrimerEnvio, $cotizacion->fresh()->mensajes()->count());
        $this->assertSame(2, $cotizacion->fresh()->mensajes()->whereNotNull('message_id_correo')->count());
        Mail::assertSent(CotizacionEnviada::class, 2);
    }

    public function test_no_se_envia_una_cotizacion_que_ya_fue_enviada(): void
    {
        Mail::fake();
        $cotizacion = $this->cotizacionConItems(['estado' => 'cotizada']);

        $this->expectException(ValidationException::class);

        app(CotizacionService::class)->enviar($cotizacion, $this->administrador());
    }

    public function test_marcar_cotizada_sin_envio_no_manda_correo(): void
    {
        Mail::fake();
        $cotizacion = $this->cotizacionConItems();

        $actualizada = app(CotizacionService::class)->marcarCotizadaSinEnvio($cotizacion, $this->administrador());

        $this->assertSame('cotizada', $actualizada->estado);
        $this->assertTrue($actualizada->mensajes()->where('autor_tipo', 'sistema')->exists());
        Mail::assertNothingSent();
    }

    public function test_marcar_cotizada_sin_envio_exige_cliente_asignado(): void
    {
        $cotizacion = Cotizacion::factory()->create(['cliente_id' => null]);
        $servicio = Servicio::factory()->create(['costo_unitario' => 1000]);
        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'servicio', 'servicio_id' => $servicio->id, 'cantidad' => 1],
        ]);

        $this->expectException(ValidationException::class);

        app(CotizacionService::class)->marcarCotizadaSinEnvio($cotizacion->fresh(), $this->administrador());
    }

    public function test_marcar_cotizada_sin_envio_exige_items(): void
    {
        $cliente = Cliente::factory()->create();
        $cotizacion = Cotizacion::factory()->create(['cliente_id' => $cliente->id]);

        $this->expectException(ValidationException::class);

        app(CotizacionService::class)->marcarCotizadaSinEnvio($cotizacion, $this->administrador());
    }
}
