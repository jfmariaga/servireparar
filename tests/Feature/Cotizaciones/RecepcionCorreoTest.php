<?php

namespace Tests\Feature\Cotizaciones;

use App\Contracts\MensajeCorreoEntrante;
use App\Contracts\ProveedorCorreoEntrante;
use App\Models\Cliente;
use App\Models\Cotizacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeProveedorCorreoEntrante;
use Tests\TestCase;

/**
 * Spec 006, US1 (FR-001): correo entrante que sigue la plantilla esperada →
 * caso "En revisión", con o sin cliente identificado automáticamente.
 */
class RecepcionCorreoTest extends TestCase
{
    use RefreshDatabase;

    public function test_correo_valido_crea_cotizacion_en_revision_con_cliente_identificado(): void
    {
        $cliente = Cliente::factory()->create(['correo' => 'juan@cliente.test']);

        $fake = new FakeProveedorCorreoEntrante();
        $fake->encolar(new MensajeCorreoEntrante(
            messageId: '<msg-1@cliente.test>',
            remitente: 'juan@cliente.test',
            asunto: 'Solicitud de Cotización — reparación de compresor',
            cuerpo: 'Buenas, quisiera cotizar la reparación de un compresor.',
        ));
        $this->app->instance(ProveedorCorreoEntrante::class, $fake);

        $this->artisan('cotizaciones:procesar-correo')->assertExitCode(0);

        $cotizacion = Cotizacion::first();
        $this->assertNotNull($cotizacion);
        $this->assertSame('en_revision', $cotizacion->estado);
        $this->assertSame($cliente->id, $cotizacion->cliente_id);
        $this->assertSame('<msg-1@cliente.test>', $cotizacion->correo_original_referencia);
        $this->assertCount(1, $fake->procesados);
    }

    public function test_correo_valido_sin_cliente_registrado_crea_cotizacion_sin_cliente(): void
    {
        $fake = new FakeProveedorCorreoEntrante();
        $fake->encolar(new MensajeCorreoEntrante(
            messageId: '<msg-2@desconocido.test>',
            remitente: 'desconocido@fuera.test',
            asunto: 'Cotización para mantenimiento',
            cuerpo: 'Necesito una cotización.',
        ));
        $this->app->instance(ProveedorCorreoEntrante::class, $fake);

        $this->artisan('cotizaciones:procesar-correo')->assertExitCode(0);

        $cotizacion = Cotizacion::first();
        $this->assertNotNull($cotizacion);
        $this->assertNull($cotizacion->cliente_id);
        $this->assertSame('<msg-2@desconocido.test>', $cotizacion->correo_original_referencia);
    }

    public function test_correo_valido_registra_el_primer_mensaje_del_hilo(): void
    {
        $fake = new FakeProveedorCorreoEntrante();
        $fake->encolar(new MensajeCorreoEntrante(
            messageId: '<msg-3@cliente.test>',
            remitente: 'ana@cliente.test',
            asunto: 'Cotización de mantenimiento preventivo',
            cuerpo: 'Cuerpo del correo original.',
        ));
        $this->app->instance(ProveedorCorreoEntrante::class, $fake);

        $this->artisan('cotizaciones:procesar-correo');

        $cotizacion = Cotizacion::first();
        $this->assertCount(1, $cotizacion->mensajes);
        $this->assertSame('cliente', $cotizacion->mensajes->first()->autor_tipo);
        $this->assertSame('Cuerpo del correo original.', $cotizacion->mensajes->first()->contenido);
    }
}
