<?php

namespace App\Events;

use App\Models\OrdenTrabajo;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado al confirmar la entrega de una OT al cliente (spec 002, FR-011).
 * Spec 008 (Notificaciones) se suscribe para avisar al cliente por correo.
 */
class OtEntregada
{
    use Dispatchable, SerializesModels;

    public function __construct(public OrdenTrabajo $ordenTrabajo) {}
}
