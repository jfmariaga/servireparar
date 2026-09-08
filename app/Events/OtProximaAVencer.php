<?php

namespace App\Events;

use App\Models\OrdenTrabajo;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado cuando una OT abierta está próxima a vencer o vencida respecto a su
 * tiempo estimado (spec 002, FR-010; umbral en `config/ot.php`). Spec 008
 * (Notificaciones) se suscribe para alertar al Jefe de Taller.
 */
class OtProximaAVencer
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public OrdenTrabajo $ordenTrabajo,
        public bool $vencida = false,
    ) {}
}
