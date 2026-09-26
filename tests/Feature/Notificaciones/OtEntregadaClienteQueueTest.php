<?php

namespace Tests\Feature\Notificaciones;

use App\Mail\OtEntregadaCliente;
use App\Models\OrdenTrabajo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

/**
 * Colas + reintento del correo al cliente (spec 008, T024/T027): antes se
 * enviaba síncrono dentro del request — si SMTP fallaba, el aviso se perdía
 * sin dejar rastro. Ahora se encola y, si falla, Laravel lo reintenta según
 * `$tries`/`backoff()` antes de moverlo a `failed_jobs`.
 */
class OtEntregadaClienteQueueTest extends TestCase
{
    public function test_el_correo_de_entrega_esta_encolado_con_reintentos(): void
    {
        $mail = new OtEntregadaCliente(new OrdenTrabajo());

        $this->assertInstanceOf(ShouldQueue::class, $mail);
        $this->assertSame(3, $mail->tries);
        $this->assertSame([60, 300, 900], $mail->backoff());
    }
}
