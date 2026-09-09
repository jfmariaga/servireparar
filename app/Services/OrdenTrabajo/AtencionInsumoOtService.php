<?php

namespace App\Services\OrdenTrabajo;

use App\Exceptions\StockInsuficienteException;
use App\Models\SolicitudInsumoOt;
use App\Models\User;
use App\Services\Inventario\MovimientoService;
use App\Services\Notificaciones\NotificadorOt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Atención por Bodega de las solicitudes de insumo generadas por las tareas de
 * una OT (spec 003, US1). Flujo de un solo paso (Phase 11 / D2):
 * `pendiente → entregada`, o `pendiente → rechazada` con motivo. No hay paso
 * intermedio de "aprobación".
 *
 * La entrega es el único punto que descuenta stock: genera un movimiento de
 * salida con `origen = 'ot'` (costeo FIFO) y enlaza la solicitud a ese
 * movimiento, de modo que el costeo de la OT tome el costo real de lo despachado.
 */
class AtencionInsumoOtService
{
    public function __construct(
        private readonly MovimientoService $movimientos = new MovimientoService(),
        private readonly NotificadorOt $notificador = new NotificadorOt(),
    ) {}

    public function entregar(SolicitudInsumoOt $solicitud, User $almacenista): SolicitudInsumoOt
    {
        $this->asegurarPendiente($solicitud);
        $solicitud->loadMissing('inventario', 'ordenTrabajo', 'tarea');

        return DB::transaction(function () use ($solicitud, $almacenista) {
            try {
                $movimiento = $this->movimientos->salida(
                    $solicitud->inventario,
                    (float) $solicitud->cantidad,
                    $almacenista,
                    origen: 'ot',
                    motivo: 'OT '.$solicitud->ordenTrabajo->numero_ot.' — '.str((string) $solicitud->tarea?->descripcion)->limit(60),
                    referencia: $solicitud->ordenTrabajo->numero_ot,
                );
            } catch (StockInsuficienteException $e) {
                throw ValidationException::withMessages(['solicitud' => $e->getMessage()]);
            }

            $solicitud->update([
                'estado' => 'entregada',
                'movimiento_id' => $movimiento->id,
                'motivo_rechazo' => null,
            ]);

            $solicitud->ordenTrabajo?->registrarEvento(
                'insumo_entregado',
                sprintf(
                    'Bodega entregó %s uds. de %s para la tarea «%s».',
                    rtrim(rtrim(number_format((float) $solicitud->cantidad, 2), '0'), '.'),
                    $solicitud->inventario->nombre,
                    str((string) $solicitud->tarea?->descripcion)->limit(40),
                ),
                $almacenista,
            );

            $this->notificador->solicitudInsumoEntregada($solicitud);

            return $solicitud->fresh();
        });
    }

    public function rechazar(SolicitudInsumoOt $solicitud, User $almacenista, string $motivo): SolicitudInsumoOt
    {
        $this->asegurarPendiente($solicitud);

        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'Indica el motivo del rechazo.']);
        }

        $solicitud->update(['estado' => 'rechazada', 'motivo_rechazo' => $motivo]);

        $solicitud->ordenTrabajo?->registrarEvento(
            'insumo_rechazado',
            sprintf('Bodega rechazó el insumo para la tarea «%s». Motivo: %s', str((string) $solicitud->tarea?->descripcion)->limit(40), $motivo),
            $almacenista,
        );

        $this->notificador->solicitudInsumoRechazada($solicitud->fresh());

        return $solicitud->fresh();
    }

    private function asegurarPendiente(SolicitudInsumoOt $solicitud): void
    {
        if ($solicitud->estado !== 'pendiente') {
            throw ValidationException::withMessages([
                'solicitud' => 'La solicitud ya fue '.$solicitud->estado.'; no admite esta acción.',
            ]);
        }
    }
}
