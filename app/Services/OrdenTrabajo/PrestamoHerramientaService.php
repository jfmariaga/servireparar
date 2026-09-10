<?php

namespace App\Services\OrdenTrabajo;

use App\Models\DetalleOt;
use App\Models\Inventario;
use App\Models\PrestamoHerramienta;
use App\Models\Tecnico;
use App\Models\User;
use App\Services\Inventario\MovimientoService;
use App\Services\Notificaciones\NotificadorOt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Préstamo de herramientas a técnicos (spec 002, Phase 12 / D15-D16, H29). El
 * técnico solicita; el Almacenista entrega o rechaza; el Almacenista registra la
 * devolución al recibirla físicamente. La OT/tarea es solo contexto y no bloquea
 * el cierre de la OT. El Jefe de Taller no interviene.
 */
class PrestamoHerramientaService
{
    /** @var array<int, string> */
    public const ESTADOS_DEVOLUCION = ['disponible', 'dañada', 'en_mantenimiento'];

    public function __construct(
        private readonly MovimientoService $movimientos = new MovimientoService(),
        private readonly NotificadorOt $notificador = new NotificadorOt(),
    ) {}

    /** El técnico pide una herramienta. La OT/tarea, si se pasan, quedan como contexto. */
    public function solicitar(Tecnico $tecnico, Inventario $herramienta, ?DetalleOt $tarea = null): PrestamoHerramienta
    {
        if ($herramienta->tipo !== 'herramienta') {
            throw ValidationException::withMessages(['herramienta' => 'Solo se pueden pedir en préstamo ítems de tipo herramienta.']);
        }

        $prestamo = PrestamoHerramienta::create([
            'ot_id' => $tarea?->ot_id,
            'tecnico_id' => $tecnico->id,
            'detalle_ot_id' => $tarea?->id,
            'inventario_id' => $herramienta->id,
            'estado' => 'solicitada',
            'solicitada_en' => now(),
        ]);

        $this->notificador->prestamoSolicitado($prestamo);

        return $prestamo;
    }

    /** El Almacenista entrega la herramienta: sale del almacén como `en_uso`. */
    public function entregar(PrestamoHerramienta $prestamo, User $almacenista): PrestamoHerramienta
    {
        $this->asegurarEstado($prestamo, 'solicitada');
        $prestamo->loadMissing('inventario');

        if ($prestamo->inventario->estado_herramienta !== 'disponible') {
            throw ValidationException::withMessages([
                'prestamo' => sprintf('«%s» no está disponible (estado: %s).',
                    $prestamo->inventario->nombre,
                    str((string) $prestamo->inventario->estado_herramienta)->replace('_', ' '),
                ),
            ]);
        }

        return DB::transaction(function () use ($prestamo, $almacenista) {
            $movimiento = $this->movimientos->salida(
                $prestamo->inventario,
                1,
                $almacenista,
                origen: 'prestamo',
                motivo: 'Préstamo de herramienta a '.($prestamo->tecnico?->usuario?->name ?? 'técnico #'.$prestamo->tecnico_id),
                referencia: $prestamo->ordenTrabajo?->numero_ot,
            );

            $prestamo->update([
                'estado' => 'entregada',
                'entregada_por' => $almacenista->id,
                'movimiento_salida_id' => $movimiento->id,
                'motivo_rechazo' => null,
            ]);

            $this->notificador->prestamoResuelto($prestamo->fresh(['inventario', 'tecnico.usuario']), true);

            return $prestamo->fresh();
        });
    }

    public function rechazar(PrestamoHerramienta $prestamo, User $almacenista, string $motivo): PrestamoHerramienta
    {
        $this->asegurarEstado($prestamo, 'solicitada');

        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'Indica el motivo del rechazo.']);
        }

        $prestamo->update([
            'estado' => 'rechazada',
            'entregada_por' => $almacenista->id,
            'motivo_rechazo' => $motivo,
        ]);

        $this->notificador->prestamoResuelto($prestamo->fresh(['inventario', 'tecnico.usuario']), false);

        return $prestamo->fresh();
    }

    /** El Almacenista registra la devolución al recibir la herramienta. */
    public function registrarDevolucion(PrestamoHerramienta $prestamo, User $almacenista, string $estadoDevolucion): PrestamoHerramienta
    {
        $this->asegurarEstado($prestamo, 'entregada');

        if (! in_array($estadoDevolucion, self::ESTADOS_DEVOLUCION, true)) {
            throw ValidationException::withMessages(['prestamo' => 'Estado de devolución inválido.']);
        }

        $prestamo->loadMissing('inventario');

        return DB::transaction(function () use ($prestamo, $almacenista, $estadoDevolucion) {
            $movimiento = $this->movimientos->devolucion(
                $prestamo->inventario,
                $almacenista,
                $estadoDevolucion,
                motivo: 'Devolución de herramienta en préstamo de '.($prestamo->tecnico?->usuario?->name ?? 'técnico #'.$prestamo->tecnico_id),
                referencia: $prestamo->ordenTrabajo?->numero_ot,
            );

            $prestamo->update([
                'estado' => 'devuelta',
                'devuelta_en' => now(),
                'estado_devolucion' => $estadoDevolucion,
                'movimiento_devolucion_id' => $movimiento->id,
                'recibida_por' => $almacenista->id,
            ]);

            return $prestamo->fresh();
        });
    }

    private function asegurarEstado(PrestamoHerramienta $prestamo, string $esperado): void
    {
        if ($prestamo->estado !== $esperado) {
            throw ValidationException::withMessages([
                'prestamo' => "El préstamo está en estado «{$prestamo->estado}»; no admite esta acción.",
            ]);
        }
    }
}
