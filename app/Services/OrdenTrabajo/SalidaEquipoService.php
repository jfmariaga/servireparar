<?php

namespace App\Services\OrdenTrabajo;

use App\Events\OtEntregada;
use App\Models\EstadoOt;
use App\Models\OrdenTrabajo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Flujo de salida de equipo y entrega al cliente (spec 002, FR-008 / FR-013):
 *
 *   solicitud (Jefe de Taller) → aprobación (SOLO Administrador) → entrega
 *
 * Si el Administrador rechaza, la OT vuelve a "En curso" con el motivo en la
 * bitácora, y el Jefe de Taller corrige y vuelve a solicitar. Toda transición
 * de estado de la OT pasa por EstadoOtService.
 */
class SalidaEquipoService
{
    public function __construct(
        private readonly EstadoOtService $estados = new EstadoOtService(),
    ) {}

    public function solicitar(OrdenTrabajo $ot, User $actor): OrdenTrabajo
    {
        $ot->loadMissing('estado');

        if (! $ot->estaEnEstado(EstadoOt::FINALIZADA)) {
            throw ValidationException::withMessages(['salida' => 'Solo se puede solicitar la salida de una OT finalizada.']);
        }

        if ($ot->valor_proyecto === null) {
            throw ValidationException::withMessages(['salida' => 'Define el valor del proyecto en el costeo (lo hace el Administrador) antes de solicitar la salida.']);
        }

        if ($ot->tieneHerramientasSinDevolver()) {
            throw ValidationException::withMessages(['salida' => 'Hay herramientas de esta OT sin devolver. Regístralas como devueltas antes de solicitar la salida del equipo.']);
        }

        if (! in_array($ot->salida_estado, ['no_solicitada', 'rechazada'], true)) {
            throw ValidationException::withMessages(['salida' => 'La salida ya fue solicitada.']);
        }

        $ot->update([
            'salida_estado' => 'solicitada',
            'salida_solicitada_por' => $actor->id,
            'salida_solicitada_en' => now(),
            'salida_resuelta_por' => null,
            'salida_resuelta_en' => null,
        ]);

        $ot->registrarEvento('salida_solicitada', 'Solicitud de salida de equipo enviada para aprobación.', $actor);

        return $ot;
    }

    public function aprobar(OrdenTrabajo $ot, User $actor): OrdenTrabajo
    {
        $this->asegurarSolicitada($ot);

        $ot->update([
            'salida_estado' => 'aprobada',
            'salida_resuelta_por' => $actor->id,
            'salida_resuelta_en' => now(),
            'salida_motivo_rechazo' => null,
        ]);

        $ot->registrarEvento('salida_aprobada', 'Salida de equipo aprobada por el Administrador.', $actor);

        return $ot;
    }

    public function rechazar(OrdenTrabajo $ot, User $actor, string $motivo): OrdenTrabajo
    {
        $this->asegurarSolicitada($ot);

        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'Indica el motivo del rechazo.']);
        }

        return DB::transaction(function () use ($ot, $actor, $motivo) {
            $ot->update([
                'salida_estado' => 'rechazada',
                'salida_resuelta_por' => $actor->id,
                'salida_resuelta_en' => now(),
                'salida_motivo_rechazo' => $motivo,
            ]);

            $ot->registrarEvento('salida_rechazada', "Salida rechazada por el Administrador. Motivo: {$motivo}", $actor);

            // FR-013: la OT vuelve a "En curso" para que el Jefe de Taller corrija.
            $this->estados->reabrir($ot->fresh(), $actor, 'Reapertura por rechazo de salida de equipo');

            return $ot->fresh(['estado']);
        });
    }

    public function confirmarEntrega(OrdenTrabajo $ot, User $actor, ?string $firmaCliente = null): OrdenTrabajo
    {
        $ot->loadMissing('estado');

        if ($ot->salida_estado !== 'aprobada') {
            throw ValidationException::withMessages(['salida' => 'La salida del equipo debe estar aprobada antes de registrar la entrega.']);
        }

        if (! $ot->estaEnEstado(EstadoOt::FINALIZADA)) {
            throw ValidationException::withMessages(['salida' => 'La OT debe estar finalizada para registrar la entrega.']);
        }

        return DB::transaction(function () use ($ot, $actor, $firmaCliente) {
            $ot->firma_cliente_url = $firmaCliente;
            $ot->save();

            $this->estados->confirmarEntrega($ot, $actor);
            $ot->registrarEvento('entrega', 'Entrega del equipo confirmada al cliente.', $actor);

            $fresca = $ot->fresh(['estado', 'cliente']);
            OtEntregada::dispatch($fresca);

            return $fresca;
        });
    }

    private function asegurarSolicitada(OrdenTrabajo $ot): void
    {
        if ($ot->salida_estado !== 'solicitada') {
            throw ValidationException::withMessages(['salida' => 'No hay una solicitud de salida pendiente de aprobación.']);
        }
    }
}
