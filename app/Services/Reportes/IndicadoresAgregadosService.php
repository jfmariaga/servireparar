<?php

namespace App\Services\Reportes;

use App\Models\DetalleOt;
use App\Models\EstadoOt;
use App\Models\OrdenTrabajo;
use App\Services\Personal\DesempenoTecnicoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Spec 007, US2 (FR-002/FR-003/FR-004): OT abiertas/cerradas/vencidas,
 * cumplimiento de tiempos y productividad del equipo — todo calculado por
 * consulta directa (sin caché), reutilizando `DesempenoTecnicoService`
 * (spec 004) para no duplicar el cálculo de desempeño por técnico.
 */
class IndicadoresAgregadosService
{
    public function __construct(private readonly DesempenoTecnicoService $desempeno = new DesempenoTecnicoService()) {}

    /**
     * OT abiertas, cerradas, vencidas y próximas a vencer. Usa el mismo umbral
     * y fórmula de límite (`created_at + tiempo_estimado_dias`) que
     * `VencimientoOtService`, para que ambos indicadores coincidan.
     *
     * @return array{abiertas: int, cerradas: int, vencidas: int, proximas_a_vencer: int}
     */
    public function otResumen(): array
    {
        $umbral = (int) config('ot.dias_umbral_vencimiento', 2);
        $hoy = Carbon::today();

        $abiertas = OrdenTrabajo::query()
            ->whereHas('estado', fn ($q) => $q->whereNotIn('slug', [EstadoOt::FINALIZADA, EstadoOt::ENTREGADA, EstadoOt::CANCELADA]))
            ->get(['id', 'created_at', 'tiempo_estimado_dias']);

        $cerradas = OrdenTrabajo::query()
            ->whereHas('estado', fn ($q) => $q->whereIn('slug', [EstadoOt::FINALIZADA, EstadoOt::ENTREGADA]))
            ->count();

        [$vencidas, $proximasAVencer] = [0, 0];

        foreach ($abiertas->whereNotNull('tiempo_estimado_dias') as $ot) {
            $limite = Carbon::parse($ot->created_at)->addDays((float) $ot->tiempo_estimado_dias)->startOfDay();
            $diasParaLimite = ($limite->getTimestamp() - $hoy->getTimestamp()) / 86400;

            if ($diasParaLimite < 0) {
                $vencidas++;
            } elseif ($diasParaLimite <= $umbral) {
                $proximasAVencer++;
            }
        }

        return [
            'abiertas' => $abiertas->count(),
            'cerradas' => $cerradas,
            'vencidas' => $vencidas,
            'proximas_a_vencer' => $proximasAVencer,
        ];
    }

    /**
     * % de OT finalizadas/entregadas cuyo total de días trabajados no superó
     * el tiempo estimado (`OrdenTrabajo::desviacionDias() <= 0`). `null` si no
     * hay OT cerradas con tiempo estimado para comparar.
     */
    public function cumplimientoTiempos(): ?float
    {
        $cerradas = OrdenTrabajo::query()
            ->whereHas('estado', fn ($q) => $q->whereIn('slug', [EstadoOt::FINALIZADA, EstadoOt::ENTREGADA]))
            ->whereNotNull('tiempo_estimado_dias')
            ->with('tareas')
            ->get();

        if ($cerradas->isEmpty()) {
            return null;
        }

        $dentroDelEstimado = $cerradas->filter(fn (OrdenTrabajo $ot) => ($ot->desviacionDias() ?? 0) <= 0)->count();

        return round($dentroDelEstimado / $cerradas->count() * 100, 1);
    }

    /**
     * Productividad del equipo: agregado de `DesempenoTecnicoService::resumenPorTecnico()`
     * sobre el rango de fechas dado.
     *
     * @return array{tecnicos_activos: int, tareas_finalizadas_total: int, cumplimiento_promedio_pct: ?float}
     */
    public function productividadEquipo(?Carbon $desde = null, ?Carbon $hasta = null): array
    {
        $porTecnico = $this->desempeno->resumenPorTecnico($desde, $hasta);
        $conCumplimiento = $porTecnico->pluck('cumplimiento_pct')->filter(fn ($v) => $v !== null);

        return [
            'tecnicos_activos' => $porTecnico->count(),
            'tareas_finalizadas_total' => (int) $porTecnico->sum('tareas_finalizadas'),
            'cumplimiento_promedio_pct' => $conCumplimiento->isNotEmpty() ? round($conCumplimiento->avg(), 1) : null,
        ];
    }

    /**
     * Tareas iniciadas y aún sin finalizar (Módulo 1, "control de tiempos"):
     * base de la vista de "trabajo en ejecución" del Jefe de Taller.
     *
     * @return Collection<int, DetalleOt>
     */
    public function tareasEnEjecucion(): Collection
    {
        return DetalleOt::query()
            ->where('estado_tarea', 'en_curso')
            ->with(['tecnico.usuario:id,name', 'ordenTrabajo:id,numero_ot,cliente_id,tiempo_estimado_dias', 'ordenTrabajo.cliente:id,nombre'])
            ->orderBy('fecha_inicio')
            ->get();
    }
}
