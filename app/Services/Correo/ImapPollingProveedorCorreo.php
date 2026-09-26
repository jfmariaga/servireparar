<?php

namespace App\Services\Correo;

use App\Contracts\MensajeCorreoEntrante;
use App\Contracts\ProveedorCorreoEntrante;
use Webklex\PHPIMAP\ClientManager;

/**
 * Implementación real de recepción vía IMAP polling (spec 006, FR-010), sobre
 * `webklex/php-imap` (sin depender de la extensión nativa `ext-imap`). Se
 * conecta con las credenciales de `config/services.php` (`services.correo_
 * cotizaciones`) — hasta que exista la cuenta de correo oficial configurada
 * en `.env`, esta clase no se ejercita (los tests usan un fake en memoria).
 */
class ImapPollingProveedorCorreo implements ProveedorCorreoEntrante
{
    /** @return array<int, MensajeCorreoEntrante> */
    public function fetchNuevosMensajes(): array
    {
        $config = config('services.correo_cotizaciones');

        $client = (new ClientManager())->make([
            'host' => $config['host'],
            'port' => $config['port'],
            'encryption' => $config['encryption'],
            'validate_cert' => true,
            'username' => $config['username'],
            'password' => $config['password'],
            'protocol' => 'imap',
        ]);

        $client->connect();
        $folder = $client->getFolder('INBOX');
        $mensajes = $folder->query()->whereUnseen()->get();

        $resultado = [];
        foreach ($mensajes as $mensaje) {
            $remitente = $mensaje->from->first();
            $referencias = array_filter(array_merge(
                $mensaje->in_reply_to?->toArray() ?? [],
                $mensaje->references?->toArray() ?? [],
            ));

            $resultado[] = new MensajeCorreoEntrante(
                messageId: (string) $mensaje->message_id->first(),
                remitente: $remitente?->mail ?? '',
                asunto: (string) $mensaje->subject->first(),
                cuerpo: $mensaje->getTextBody() ?: strip_tags($mensaje->getHTMLBody()),
                referencias: array_values($referencias),
            );
        }

        return $resultado;
    }

    public function marcarProcesado(MensajeCorreoEntrante $mensaje): void
    {
        // El estado "procesado" se rastrea por `message_id_correo` ya guardado en
        // `mensajes_cotizacion` (evita reprocesar); marcar el correo como leído en
        // el buzón real es un detalle de despliegue que se ajusta cuando exista la
        // cuenta oficial (fuera de alcance de este corte).
    }
}
