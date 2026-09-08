<?php

namespace App\Services\OrdenTrabajo;

use App\Models\OrdenTrabajo;
use Illuminate\Support\Carbon;

/**
 * Costeo y utilidad neta por OT (spec 002, User Story 5 / FR-016), réplica del
 * cálculo manual del formato Excel real del cliente:
 *
 *   costo_mano_obra_propia = Σ (dias_trabajados × valor_día del técnico)
 *       valor_día = sueldo_mensual / config('personal.dias_mes') del sueldo
 *       VIGENTE a la fecha de referencia de la OT (su fecha de finalización, o
 *       hoy si sigue abierta). Una OT cerrada NO se recostea si el sueldo del
 *       técnico cambia después (spec 004, FR-011).
 *   costo_contratistas = Σ ot_mano_obra_contratista.valor
 *   costo_repuestos    = Σ (cantidad × costo unitario real del consumo, o el de
 *                          referencia del ítem si aún no se despachó) [spec 003]
 *   costo_total        = suma de los tres
 *   utilidad_neta      = valor_proyecto - costo_total  (valor_proyecto null → 0)
 *
 * Aislado de la UI para que spec 007 (Reportes/KPIs) lo reutilice sin duplicar.
 */
class CosteoOtService
{
    /**
     * @return array{
     *   fecha_referencia: \Illuminate\Support\Carbon,
     *   mano_obra_propia: float,
     *   contratistas: float,
     *   repuestos: float,
     *   costo_total: float,
     *   valor_proyecto: float|null,
     *   utilidad_neta: float
     * }
     */
    public function calcular(OrdenTrabajo $ot): array
    {
        $ot->loadMissing([
            'tareas.tecnico.sueldos',
            'manoObraContratistas',
            'tareas.solicitudInsumo.inventario',
            'tareas.solicitudInsumo.movimiento',
            'tareas.insumo',
        ]);

        $fechaRef = $ot->fecha_finalizacion
            ? Carbon::parse($ot->fecha_finalizacion)
            : Carbon::today();

        $manoObraPropia = $ot->tareas->reduce(function (float $acc, $tarea) use ($fechaRef) {
            $dias = (float) ($tarea->dias_trabajados ?? 0);

            if ($dias <= 0 || ! $tarea->tecnico) {
                return $acc;
            }

            return $acc + $dias * (float) ($tarea->tecnico->valorDia($fechaRef) ?? 0);
        }, 0.0);

        $contratistas = (float) $ot->manoObraContratistas->sum('valor');

        $repuestos = $ot->tareas->reduce(function (float $acc, $tarea) {
            if (! $tarea->requiereInsumo()) {
                return $acc;
            }

            $cantidad = (float) $tarea->cantidad_insumo;

            // Si Bodega ya despachó el insumo, usa el costo REAL de ese movimiento
            // (costeo FIFO); si no, cae al costo de referencia del ítem.
            $costoUnit = $tarea->solicitudInsumo?->movimiento?->costo_unitario
                ?? $tarea->solicitudInsumo?->inventario?->costo_unitario
                ?? $tarea->insumo?->costo_unitario
                ?? 0;

            return $acc + $cantidad * (float) $costoUnit;
        }, 0.0);

        $costoTotal = round($manoObraPropia + $contratistas + $repuestos, 2);
        $valorProyecto = $ot->valor_proyecto !== null ? (float) $ot->valor_proyecto : null;

        return [
            'fecha_referencia' => $fechaRef,
            'mano_obra_propia' => round($manoObraPropia, 2),
            'contratistas' => round($contratistas, 2),
            'repuestos' => round($repuestos, 2),
            'costo_total' => $costoTotal,
            'valor_proyecto' => $valorProyecto,
            'utilidad_neta' => round(($valorProyecto ?? 0) - $costoTotal, 2),
        ];
    }
}
