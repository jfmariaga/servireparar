<?php

namespace App\Contracts;

/**
 * Obtiene correos nuevos de la cuenta oficial (spec 006, FR-010). La
 * implementación real (`ImapPollingProveedorCorreo`) consulta vía IMAP; los
 * tests usan un fake en memoria — nada del resto del módulo depende del
 * protocolo concreto.
 */
interface ProveedorCorreoEntrante
{
    /** @return array<int, MensajeCorreoEntrante> */
    public function fetchNuevosMensajes(): array;

    public function marcarProcesado(MensajeCorreoEntrante $mensaje): void;
}
