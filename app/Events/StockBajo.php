<?php

namespace App\Events;

use App\Models\Inventario;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado cuando el stock de un consumible cae por debajo de su stock mínimo
 * (spec 003, FR-007). Spec 008 (Notificaciones) se suscribe a este evento.
 */
class StockBajo
{
    use Dispatchable, SerializesModels;

    public function __construct(public Inventario $inventario) {}
}
