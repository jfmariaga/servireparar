<?php

namespace App\Contracts;

use App\Models\Cotizacion;

/**
 * Envía una Cotización al cliente por correo (spec 006, FR-004), con el PDF
 * generado internamente (`GenerarPdfCotizacionService`). Devuelve el
 * Message-ID del correo enviado, que se guarda en `mensajes_cotizacion` para
 * matchear la respuesta del cliente en el mismo hilo (US3), sin depender de
 * si la Cotización tiene cliente identificado automáticamente.
 */
interface ProveedorCorreoSaliente
{
    public function enviar(Cotizacion $cotizacion, string $destinatario): string;
}
