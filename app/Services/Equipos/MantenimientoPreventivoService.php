<?php

namespace App\Services\Equipos;

use App\Events\MantenimientoPreventivoProximoAVencer;
use App\Models\Equipo;
use App\Models\MantenimientoPreventivo;
use Carbon\Carbon;
use RuntimeException;

/**
 * Calcula y revisa la programación de mantenimiento preventivo (spec 005, FR-005/FR-006).
 * Se dispara manualmente al completar un mantenimiento — spec 002 (OT) invocará
 * registrarMantenimientoCompletado() al finalizar una OT de mantenimiento una vez integrado.
 */
class MantenimientoPreventivoService
{
    public function registrarMantenimientoCompletado(Equipo $equipo, ?Carbon $fecha = null): MantenimientoPreventivo
    {
        if (! $equipo->tienePreventivoProgramado()) {
            throw new RuntimeException('El equipo no tiene periodicidad de mantenimiento preventivo configurada.');
        }

        $fecha ??= now();
        $proximaFecha = $fecha->copy()->addDays($equipo->periodicidad_mantenimiento_dias);

        return MantenimientoPreventivo::updateOrCreate(
            ['equipo_id' => $equipo->id],
            [
                'ultima_fecha' => $fecha->toDateString(),
                'proxima_fecha' => $proximaFecha->toDateString(),
                'alerta_disparada' => false,
            ]
        );
    }

    /**
     * Dispara el evento de alerta para cada mantenimiento próximo a vencer aún no notificado.
     *
     * @return int Cantidad de alertas disparadas
     */
    public function revisarVencimientos(int $diasAntelacion = 7): int
    {
        $pendientes = MantenimientoPreventivo::proximosAVencer($diasAntelacion)->with('equipo')->get();

        foreach ($pendientes as $mantenimiento) {
            event(new MantenimientoPreventivoProximoAVencer($mantenimiento));
            $mantenimiento->update(['alerta_disparada' => true]);
        }

        return $pendientes->count();
    }
}
