<?php

namespace App\Services\OrdenTrabajo;

use App\Models\DetalleOt;
use App\Models\DetalleOtInsumo;
use App\Models\Inventario;
use App\Models\SolicitudInsumoOt;
use App\Models\User;
use App\Services\Notificaciones\NotificadorOt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Punto de integración OT → Bodega (spec 002, FR-003; spec 003 US1). Sincroniza
 * las líneas de insumo de una tarea (`detalle_ot_insumos`) con sus solicitudes
 * hacia Bodega. Una tarea puede tener N líneas de insumo (Phase 11 / D6).
 *
 * NO descuenta stock: eso ocurre cuando el Almacenista entrega la solicitud
 * (spec 003). Nunca borra una solicitud en silencio: al quitar una línea, la
 * solicitud pendiente pasa a `cancelada` y queda el evento en `ot_eventos` (H11).
 */
class SolicitudInsumoService
{
    public function __construct(
        private readonly NotificadorOt $notificador = new NotificadorOt(),
    ) {}

    /**
     * Deja las líneas de insumo de la tarea (y sus solicitudes) igual a
     * `$lineasDeseadas` (lista de `['inventario_id' => int, 'cantidad' => float]`,
     * sin ítems repetidos). Devuelve la tarea recargada.
     *
     * @param  array<int, array{inventario_id: int, cantidad: float}>  $lineasDeseadas
     */
    public function aplicarLineasInsumo(DetalleOt $tarea, array $lineasDeseadas, ?User $actor = null): DetalleOt
    {
        $tarea = DB::transaction(function () use ($tarea, $lineasDeseadas, $actor, &$creadas) {
            $creadas = 0;
            $tarea->loadMissing('insumos', 'solicitudesInsumo', 'ordenTrabajo');

            $deseadas = collect($lineasDeseadas)
                ->filter(fn ($l) => (int) ($l['inventario_id'] ?? 0) > 0 && (float) ($l['cantidad'] ?? 0) > 0)
                ->keyBy(fn ($l) => (int) $l['inventario_id']);

            $solicitudPorLinea = $tarea->solicitudesInsumo->keyBy('detalle_ot_insumo_id');

            // 1. Quitar líneas que ya no se piden.
            foreach ($tarea->insumos as $linea) {
                if ($deseadas->has((int) $linea->inventario_id)) {
                    continue;
                }

                $solicitud = $solicitudPorLinea->get($linea->id);
                $this->asegurarNoProcesada($solicitud, $linea, 'quitar');
                $this->cancelarSolicitud($solicitud, $linea, $actor);
                $linea->delete();
            }

            // 2. Crear / actualizar las líneas pedidas y sincronizar su solicitud.
            foreach ($deseadas as $invId => $l) {
                $cantidad = round((float) $l['cantidad'], 2);

                $linea = $tarea->insumos->firstWhere('inventario_id', $invId)
                    ?? new DetalleOtInsumo(['detalle_ot_id' => $tarea->id, 'inventario_id' => $invId]);

                $solicitud = $linea->exists ? $solicitudPorLinea->get($linea->id) : null;

                if ($linea->exists && round((float) $linea->cantidad, 2) !== $cantidad) {
                    $this->asegurarNoProcesada($solicitud, $linea, 'cambiar la cantidad de');
                }

                $this->asegurarStockDisponible($invId, $cantidad, $solicitud);

                $linea->cantidad = $cantidad;
                $linea->detalle_ot_id = $tarea->id;
                $linea->inventario_id = $invId;
                $linea->save();

                if ($this->sincronizarSolicitud($tarea, $linea, $solicitud, $actor)) {
                    $creadas++;
                }
            }

            return $tarea->fresh(['insumos.inventario', 'solicitudesInsumo', 'ordenTrabajo.cliente']);
        });

        if (($creadas ?? 0) > 0 && $tarea->ordenTrabajo) {
            $this->notificador->solicitudInsumoCreada($tarea->ordenTrabajo, $creadas);
        }

        return $tarea;
    }

    /**
     * Bloqueo duro de stock (Phase 11 / D1, H1/H2): una línea de insumo consumible
     * no puede pedir más de lo que hay realmente disponible = `stock_actual` menos
     * lo ya comprometido por otras solicitudes sin despachar. No se bloquea mantener
     * o reducir una reserva existente, solo crearla o aumentarla por encima del
     * disponible (evita atascar el guardado si el ítem ya venía sobre-comprometido).
     */
    private function asegurarStockDisponible(int $inventarioId, float $cantidad, ?SolicitudInsumoOt $solicitudActual): void
    {
        // lockForUpdate: serializa el chequeo entre OT que reservan el mismo ítem
        // a la vez (estamos dentro de la transacción de aplicarLineasInsumo).
        $item = Inventario::whereKey($inventarioId)->lockForUpdate()->first();

        if (! $item || $item->tipo !== 'consumible') {
            return;
        }

        $reservadoPorEstaLinea = $solicitudActual && $solicitudActual->estado === 'pendiente'
            ? (float) $solicitudActual->cantidad
            : 0.0;

        // No empeora la situación: mantener o reducir siempre se permite.
        if ($cantidad <= $reservadoPorEstaLinea) {
            return;
        }

        $comprometidoPorOtras = (float) SolicitudInsumoOt::pendientesDe($inventarioId)
            ->when($solicitudActual?->id, fn ($q) => $q->whereKeyNot($solicitudActual->id))
            ->sum('cantidad');

        $disponible = (float) $item->stock_actual - $comprometidoPorOtras;

        if ($cantidad > $disponible) {
            throw ValidationException::withMessages([
                'tarea' => sprintf(
                    'Solo hay %s uds. disponibles de «%s» (%s ya comprometidas en otras solicitudes). No se puede reservar %s.',
                    $this->nfmt(max($disponible, 0)),
                    $item->nombre,
                    $this->nfmt($comprometidoPorOtras),
                    $this->nfmt($cantidad),
                ),
            ]);
        }
    }

    /**
     * Una línea cuya solicitud Bodega ya aprobó o entregó no se puede quitar ni
     * re-cantidad desde la OT: el material ya salió del almacén. Si sobra, se
     * registra una devolución en Inventario (H11 — no se desincroniza en silencio).
     */
    private function asegurarNoProcesada(?SolicitudInsumoOt $solicitud, DetalleOtInsumo $linea, string $accion): void
    {
        if (! $solicitud || $solicitud->estado !== 'entregada') {
            return;
        }

        $linea->loadMissing('inventario');

        throw ValidationException::withMessages([
            'tarea' => sprintf(
                'No se puede %s el insumo «%s»: Bodega ya lo entregó. Si sobra, regístralo como devolución en Inventario.',
                $accion,
                $linea->inventario?->nombre ?? 'ítem #'.$linea->inventario_id,
            ),
        ]);
    }

    private function cancelarSolicitud(?SolicitudInsumoOt $solicitud, DetalleOtInsumo $linea, ?User $actor): void
    {
        if (! $solicitud || $solicitud->estado !== 'pendiente') {
            return;
        }

        $solicitud->update(['estado' => 'cancelada']);

        $linea->loadMissing('inventario');
        $solicitud->ordenTrabajo?->registrarEvento(
            'insumo_cancelado',
            sprintf(
                'Se retiró el insumo «%s» (%s uds.) de la tarea «%s».',
                $linea->inventario?->nombre ?? 'ítem #'.$linea->inventario_id,
                $this->nfmt($linea->cantidad),
                str((string) $linea->tarea?->descripcion)->limit(40),
            ),
            $actor,
        );
    }

    /** Devuelve true si creó una solicitud nueva (para avisar a Bodega). */
    private function sincronizarSolicitud(DetalleOt $tarea, DetalleOtInsumo $linea, ?SolicitudInsumoOt $solicitud, ?User $actor): bool
    {
        // Bodega ya la entregó: no se toca.
        if ($solicitud && $solicitud->estado === 'entregada') {
            return false;
        }

        if ($solicitud) {
            $cambioCantidad = round((float) $solicitud->cantidad, 2) !== round((float) $linea->cantidad, 2);
            $cambioItem = (int) $solicitud->inventario_id !== (int) $linea->inventario_id;

            $solicitud->update([
                'inventario_id' => $linea->inventario_id,
                'cantidad' => $linea->cantidad,
                'estado' => 'pendiente',
                'motivo_rechazo' => null,
            ]);

            if ($cambioCantidad || $cambioItem) {
                $tarea->ordenTrabajo?->registrarEvento(
                    'insumo_modificado',
                    sprintf(
                        'Se ajustó el insumo de la tarea «%s»: %s uds. de %s.',
                        str($tarea->descripcion)->limit(40),
                        $this->nfmt($linea->cantidad),
                        $linea->inventario?->nombre ?? 'ítem #'.$linea->inventario_id,
                    ),
                    $actor,
                );
            }

            return false;
        }

        $nueva = SolicitudInsumoOt::create([
            'ot_id' => $tarea->ot_id,
            'detalle_ot_id' => $tarea->id,
            'detalle_ot_insumo_id' => $linea->id,
            'inventario_id' => $linea->inventario_id,
            'cantidad' => $linea->cantidad,
            'estado' => 'pendiente',
            'solicitada_por' => $actor?->id ?? auth()->id(),
        ]);

        $linea->loadMissing('inventario');
        $tarea->ordenTrabajo?->registrarEvento(
            'insumo_solicitado',
            sprintf(
                'Solicitud de insumo generada para la tarea «%s»: %s uds. de %s.',
                str($tarea->descripcion)->limit(40),
                $this->nfmt($nueva->cantidad),
                $linea->inventario?->nombre ?? 'ítem #'.$linea->inventario_id,
            ),
            $actor,
        );

        return true;
    }

    private function nfmt(float|string|null $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    }
}
