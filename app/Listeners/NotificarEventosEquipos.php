<?php

namespace App\Listeners;

use App\Enums\RolPrioridad;
use App\Events\MantenimientoPreventivoProximoAVencer;
use App\Models\User;
use App\Notifications\OtNotificacion;
use Illuminate\Support\Facades\Notification;

/**
 * Traduce los eventos de dominio de Equipos (spec 005) en avisos in-app para
 * la campana — mismo patrón que `NotificarEventosOt`, en un listener aparte
 * porque el evento documenta explícitamente que 005 no conoce a 008
 * directamente (ver `MantenimientoPreventivoProximoAVencer`).
 */
class NotificarEventosEquipos
{
    public function mantenimientoProximoAVencer(MantenimientoPreventivoProximoAVencer $event): void
    {
        $mantenimiento = $event->mantenimiento;
        $equipo = $mantenimiento->equipo;
        $vencido = $mantenimiento->proxima_fecha->isPast();

        $destinatarios = User::query()
            ->where('estado', 'activo')
            ->role([RolPrioridad::Administrador->value, RolPrioridad::JefeDeTaller->value])
            ->get();

        $descripcionEquipo = trim($equipo?->tipo.' '.$equipo?->marca.' '.$equipo?->modelo);

        Notification::send($destinatarios, new OtNotificacion(
            $vencido ? 'Mantenimiento preventivo vencido' : 'Mantenimiento preventivo próximo a vencer',
            "«{$descripcionEquipo}» ({$equipo?->cliente?->nombre}) — próximo mantenimiento el ".$mantenimiento->proxima_fecha->format('d/m/Y').'.',
            route('equipos.historial', $equipo),
            'alerta',
        ));
    }
}
