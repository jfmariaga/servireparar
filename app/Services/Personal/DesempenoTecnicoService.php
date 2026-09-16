<?php

namespace App\Services\Personal;

use App\Models\DetalleOt;
use App\Models\Tecnico;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Spec 004, US3 (FR-003/FR-004): tiempos de ejecución, participación en OT y
 * productividad por técnico. Aislado de la UI para que spec 007 lo reutilice
 * en el indicador de "productividad del equipo" sin duplicar el cálculo.
 */
class DesempenoTecnicoService
{
    /**
     * Resumen de desempeño de un técnico, filtrado por la fecha de finalización
     * de sus tareas (`fecha_fin`) dentro del rango dado.
     *
     * @return array{ot_count: int, tareas_finalizadas: int, tiempo_promedio_dias: ?float, cumplimiento_pct: ?float}
     */
    public function resumen(Tecnico $tecnico, ?Carbon $desde = null, ?Carbon $hasta = null): array
    {
        $finalizadas = DetalleOt::query()
            ->where('tecnico_id', $tecnico->id)
            ->where('estado_tarea', 'finalizada')
            ->when($desde, fn ($q) => $q->whereDate('fecha_fin', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('fecha_fin', '<=', $hasta))
            ->get();

        $tareasFinalizadas = $finalizadas->count();

        $conPlazo = $finalizadas->filter(fn (DetalleOt $t) => $t->dias_cumplimiento !== null);

        return [
            'ot_count' => $finalizadas->pluck('ot_id')->unique()->count(),
            'tareas_finalizadas' => $tareasFinalizadas,
            'tiempo_promedio_dias' => $tareasFinalizadas > 0
                ? round((float) $finalizadas->avg('dias_trabajados'), 2)
                : null,
            'cumplimiento_pct' => $conPlazo->isNotEmpty()
                ? round(
                    $conPlazo->filter(fn (DetalleOt $t) => (float) $t->dias_trabajados <= (float) $t->dias_cumplimiento)->count()
                        / $conPlazo->count() * 100,
                    1
                )
                : null,
        ];
    }

    /**
     * Resumen por técnico (solo los que tienen al menos una tarea finalizada en
     * el rango) — base del reporte de desempeño y de la productividad del
     * equipo agregada (spec 007, FR-004).
     *
     * @return Collection<int, array{tecnico_id: int, nombre: string, ot_count: int, tareas_finalizadas: int, tiempo_promedio_dias: ?float, cumplimiento_pct: ?float}>
     */
    public function resumenPorTecnico(?Carbon $desde = null, ?Carbon $hasta = null): Collection
    {
        return Tecnico::query()->with('usuario:id,name')->get()
            ->map(fn (Tecnico $t) => array_merge(
                ['tecnico_id' => $t->id, 'nombre' => $t->usuario?->name ?? 'Técnico #'.$t->id],
                $this->resumen($t, $desde, $hasta),
            ))
            ->filter(fn (array $r) => $r['tareas_finalizadas'] > 0)
            ->values();
    }
}
