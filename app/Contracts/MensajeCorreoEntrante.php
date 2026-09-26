<?php

namespace App\Contracts;

/**
 * DTO de un correo entrante ya parseado, independiente del protocolo/librería
 * que lo obtuvo (spec 006, FR-010). `ProveedorCorreoEntrante` siempre entrega
 * objetos de este tipo — el resto del módulo nunca toca IMAP directamente.
 */
final class MensajeCorreoEntrante
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $remitente,
        public readonly string $asunto,
        public readonly string $cuerpo,
        /** @var array<int, string> Message-IDs referenciados (cabeceras In-Reply-To/References), si es una respuesta */
        public readonly array $referencias = [],
    ) {}

    public function esRespuesta(): bool
    {
        return $this->referencias !== [];
    }
}
