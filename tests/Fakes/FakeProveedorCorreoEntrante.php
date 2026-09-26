<?php

namespace Tests\Fakes;

use App\Contracts\MensajeCorreoEntrante;
use App\Contracts\ProveedorCorreoEntrante;

/**
 * Fake en memoria de `ProveedorCorreoEntrante` (spec 006) — permite testear
 * `ProcesarCorreoEntranteService` y el comando de polling sin IMAP real.
 */
class FakeProveedorCorreoEntrante implements ProveedorCorreoEntrante
{
    /** @var array<int, MensajeCorreoEntrante> */
    private array $pendientes = [];

    /** @var array<int, MensajeCorreoEntrante> */
    public array $procesados = [];

    public function encolar(MensajeCorreoEntrante $mensaje): static
    {
        $this->pendientes[] = $mensaje;

        return $this;
    }

    /** @return array<int, MensajeCorreoEntrante> */
    public function fetchNuevosMensajes(): array
    {
        $mensajes = $this->pendientes;
        $this->pendientes = [];

        return $mensajes;
    }

    public function marcarProcesado(MensajeCorreoEntrante $mensaje): void
    {
        $this->procesados[] = $mensaje;
    }
}
