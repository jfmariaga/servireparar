<?php

namespace App\Listeners;

use App\Events\OtCreada;
use App\Events\OtEntregada;
use App\Events\OtProximaAVencer;
use App\Events\StockBajo;
use App\Mail\OtEntregadaCliente;
use App\Services\Notificaciones\NotificadorOt;
use Illuminate\Support\Facades\Mail;

/**
 * Traduce los eventos de dominio de OT / stock (spec 002/003) en avisos in-app
 * para la campana y, en la entrega, el correo al cliente (FR-011). Phase 11 / D5.
 */
class NotificarEventosOt
{
    public function __construct(private readonly NotificadorOt $notificador) {}

    public function otCreada(OtCreada $event): void
    {
        $this->notificador->otCreada($event->ordenTrabajo);
    }

    public function otEntregada(OtEntregada $event): void
    {
        $ot = $event->ordenTrabajo;
        $this->notificador->otEntregada($ot);

        $correo = $ot->cliente?->correo;
        if ($correo) {
            Mail::to($correo)->send(new OtEntregadaCliente($ot));
        }
    }

    public function otProximaAVencer(OtProximaAVencer $event): void
    {
        $this->notificador->otProximaAVencer($event->ordenTrabajo, $event->vencida);
    }

    public function stockBajo(StockBajo $event): void
    {
        $this->notificador->stockBajo($event->inventario);
    }
}
