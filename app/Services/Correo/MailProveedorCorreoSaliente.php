<?php

namespace App\Services\Correo;

use App\Contracts\ProveedorCorreoSaliente;
use App\Mail\CotizacionEnviada;
use App\Models\Cotizacion;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Implementación concreta de envío saliente sobre Laravel Mail/SMTP (spec 006,
 * FR-010). Genera el Message-ID explícitamente para poder guardarlo y
 * matchear la respuesta del cliente en el mismo hilo (US3).
 */
class MailProveedorCorreoSaliente implements ProveedorCorreoSaliente
{
    public function enviar(Cotizacion $cotizacion, array $destinatarios): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'serviops.local';
        $messageId = sprintf('<cot-%s-%s@%s>', $cotizacion->numero, (string) Str::uuid(), $host);

        Mail::to($destinatarios)->send(new CotizacionEnviada($cotizacion, $messageId));

        return $messageId;
    }
}
