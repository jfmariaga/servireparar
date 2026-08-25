<?php

namespace App\Events;

use App\Models\MantenimientoPreventivo;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado cuando un mantenimiento preventivo está próximo a vencer (spec 005, FR-006).
 * Spec 008 (Notificaciones) se suscribe a este evento — 005 no conoce a 008 directamente.
 */
class MantenimientoPreventivoProximoAVencer
{
    use Dispatchable, SerializesModels;

    public function __construct(public MantenimientoPreventivo $mantenimiento) {}
}
