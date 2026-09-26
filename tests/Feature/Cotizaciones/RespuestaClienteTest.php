<?php

namespace Tests\Feature\Cotizaciones;

use App\Contracts\MensajeCorreoEntrante;
use App\Contracts\ProveedorCorreoEntrante;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Servicio;
use App\Services\Cotizaciones\CotizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Fakes\FakeProveedorCorreoEntrante;
use Tests\TestCase;

/**
 * Spec 006, US3 (FR-005, SC-003): la respuesta del cliente en el mismo hilo
 * (mismo Message-ID en References) cambia el estado automáticamente, sin
 * intervención manual del Administrador.
 */
class RespuestaClienteTest extends TestCase
{
    use RefreshDatabase;

    private function cotizacionEnviada(): Cotizacion
    {
        Mail::fake();
        $cliente = Cliente::factory()->create(['correo' => 'cliente@ejemplo.com']);
        $cotizacion = Cotizacion::factory()->create(['cliente_id' => $cliente->id, 'estado' => 'en_revision']);
        $servicio = Servicio::factory()->create();
        app(CotizacionService::class)->guardarItems($cotizacion, [
            ['tipo_item' => 'servicio', 'servicio_id' => $servicio->id, 'cantidad' => 1],
        ]);
        app(CotizacionService::class)->enviar($cotizacion->fresh(), \App\Models\User::factory()->create());

        return $cotizacion->fresh();
    }

    public function test_respuesta_con_aceptacion_en_el_mismo_hilo_cambia_a_aceptada(): void
    {
        $cotizacion = $this->cotizacionEnviada();
        $messageIdEnviado = $cotizacion->mensajes()->latest('id')->first()->message_id_correo;

        $fake = new FakeProveedorCorreoEntrante();
        $fake->encolar(new MensajeCorreoEntrante(
            messageId: '<respuesta-1@cliente.test>',
            remitente: 'cliente@ejemplo.com',
            asunto: 'RE: Cotización '.$cotizacion->numero,
            cuerpo: 'La acepto, procedan con el servicio.',
            referencias: [$messageIdEnviado],
        ));
        $this->app->instance(ProveedorCorreoEntrante::class, $fake);

        $this->artisan('cotizaciones:procesar-correo');

        $this->assertSame('aceptada', $cotizacion->fresh()->estado);
    }

    public function test_respuesta_con_rechazo_en_el_mismo_hilo_cambia_a_rechazada(): void
    {
        $cotizacion = $this->cotizacionEnviada();
        $messageIdEnviado = $cotizacion->mensajes()->latest('id')->first()->message_id_correo;

        $fake = new FakeProveedorCorreoEntrante();
        $fake->encolar(new MensajeCorreoEntrante(
            messageId: '<respuesta-2@cliente.test>',
            remitente: 'cliente@ejemplo.com',
            asunto: 'RE: Cotización '.$cotizacion->numero,
            cuerpo: 'No nos interesa por ahora, gracias.',
            referencias: [$messageIdEnviado],
        ));
        $this->app->instance(ProveedorCorreoEntrante::class, $fake);

        $this->artisan('cotizaciones:procesar-correo');

        $this->assertSame('rechazada', $cotizacion->fresh()->estado);
    }

    public function test_avance_manual_aceptada_entregada_facturada_deja_historial(): void
    {
        $cotizacion = $this->cotizacionEnviada();
        $cotizacion->update(['estado' => 'aceptada']);

        app(CotizacionService::class)->marcarEntregada($cotizacion->fresh());
        app(CotizacionService::class)->marcarFacturada($cotizacion->fresh());

        $this->assertSame('facturada', $cotizacion->fresh()->estado);
        $this->assertTrue($cotizacion->fresh()->mensajes()->where('contenido', 'like', '%entregada%')->exists());
        $this->assertTrue($cotizacion->fresh()->mensajes()->where('contenido', 'like', '%facturada%')->exists());
    }
}
