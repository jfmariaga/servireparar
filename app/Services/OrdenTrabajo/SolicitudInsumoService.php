<?php

namespace App\Services\OrdenTrabajo;

use App\Models\DetalleOt;
use App\Models\SolicitudInsumoOt;

/**
 * Punto de integración OT → Bodega (spec 002, FR-003; spec 003 US1). Al guardar
 * una tarea con insumo y cantidad, genera (o sincroniza) una solicitud de
 * insumo `pendiente` que el Almacenista atiende desde Inventario. NO descuenta
 * stock: eso ocurre cuando el Almacenista entrega la solicitud (spec 003).
 */
class SolicitudInsumoService
{
    /**
     * Crea/actualiza la solicitud de insumo de una tarea. Devuelve la solicitud
     * si la tarea requiere insumo, o null si no. Una solicitud ya aprobada o
     * entregada no se toca (el almacén ya la procesó).
     */
    public function sincronizarDesdeTarea(DetalleOt $tarea): ?SolicitudInsumoOt
    {
        $tarea->loadMissing('solicitudInsumo');
        $solicitud = $tarea->solicitudInsumo;

        if (! $tarea->requiereInsumo()) {
            if ($solicitud && $solicitud->estado === 'pendiente') {
                $solicitud->delete();
            }

            return null;
        }

        if ($solicitud && in_array($solicitud->estado, ['aprobada', 'entregada'], true)) {
            return $solicitud;
        }

        if ($solicitud) {
            $solicitud->update([
                'inventario_id' => $tarea->insumo_id,
                'cantidad' => $tarea->cantidad_insumo,
                'estado' => 'pendiente',
                'motivo_rechazo' => null,
            ]);

            return $solicitud;
        }

        $solicitud = SolicitudInsumoOt::create([
            'ot_id' => $tarea->ot_id,
            'detalle_ot_id' => $tarea->id,
            'inventario_id' => $tarea->insumo_id,
            'cantidad' => $tarea->cantidad_insumo,
            'estado' => 'pendiente',
            'solicitada_por' => auth()->id(),
        ]);

        $tarea->ordenTrabajo?->registrarEvento(
            'insumo_solicitado',
            sprintf('Solicitud de insumo generada para la tarea «%s» (%s uds.)', str($tarea->descripcion)->limit(40), rtrim(rtrim(number_format((float) $tarea->cantidad_insumo, 2), '0'), '.')),
        );

        return $solicitud;
    }
}
