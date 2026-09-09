<?php

namespace App\Services\OrdenTrabajo;

use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use App\Models\OtHerramienta;
use App\Models\User;
use App\Services\Inventario\MovimientoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Asignación y devolución de herramientas de inventario en una OT (spec 002,
 * Phase 11 / D4, H9). Asignar saca la herramienta del almacén (`en_uso`) con un
 * movimiento `salida` origen `ot`; devolver la reingresa con un estado explícito
 * (`disponible` / `dañada` / `en_mantenimiento`) vía un movimiento `devolucion`.
 * Toda herramienta asignada debe devolverse antes de solicitar la salida del equipo.
 */
class OtHerramientaService
{
    /** @var array<int, string> */
    public const ESTADOS_DEVOLUCION = ['disponible', 'dañada', 'en_mantenimiento'];

    public function __construct(
        private readonly MovimientoService $movimientos = new MovimientoService(),
    ) {}

    public function asignar(OrdenTrabajo $ot, Inventario $herramienta, User $actor): OtHerramienta
    {
        if ($herramienta->tipo !== 'herramienta') {
            throw ValidationException::withMessages(['herramienta' => 'Solo se pueden asignar ítems de tipo herramienta.']);
        }

        if ($ot->estaBloqueada()) {
            throw ValidationException::withMessages(['herramienta' => 'La OT está congelada: no admite asignar herramientas.']);
        }

        if ($herramienta->estado_herramienta !== 'disponible') {
            throw ValidationException::withMessages([
                'herramienta' => sprintf('«%s» no está disponible (estado: %s).', $herramienta->nombre, str((string) $herramienta->estado_herramienta)->replace('_', ' ')),
            ]);
        }

        if ($ot->herramientas()->where('inventario_id', $herramienta->id)->whereNull('devuelta_en')->exists()) {
            throw ValidationException::withMessages(['herramienta' => 'Esa herramienta ya está asignada a esta OT.']);
        }

        return DB::transaction(function () use ($ot, $herramienta, $actor) {
            $movimiento = $this->movimientos->salida(
                $herramienta,
                1,
                $actor,
                origen: 'ot',
                motivo: 'OT '.$ot->numero_ot.' — asignación de herramienta',
                referencia: $ot->numero_ot,
            );

            $asignacion = $ot->herramientas()->create([
                'inventario_id' => $herramienta->id,
                'asignada_por' => $actor->id,
                'asignada_en' => now(),
                'movimiento_salida_id' => $movimiento->id,
            ]);

            $ot->registrarEvento('herramienta_asignada', "Herramienta «{$herramienta->nombre}» asignada a la OT.", $actor);

            return $asignacion;
        });
    }

    public function devolver(OtHerramienta $asignacion, User $actor, string $estadoDevolucion): OtHerramienta
    {
        if ($asignacion->estaDevuelta()) {
            throw ValidationException::withMessages(['herramienta' => 'Esta herramienta ya fue devuelta.']);
        }

        if (! in_array($estadoDevolucion, self::ESTADOS_DEVOLUCION, true)) {
            throw ValidationException::withMessages(['herramienta' => 'Estado de devolución inválido.']);
        }

        $asignacion->loadMissing('inventario', 'ordenTrabajo');

        return DB::transaction(function () use ($asignacion, $actor, $estadoDevolucion) {
            $movimiento = $this->movimientos->devolucion(
                $asignacion->inventario,
                $actor,
                $estadoDevolucion,
                motivo: 'OT '.$asignacion->ordenTrabajo->numero_ot.' — devolución de herramienta',
                referencia: $asignacion->ordenTrabajo->numero_ot,
            );

            $asignacion->update([
                'devuelta_en' => now(),
                'estado_devolucion' => $estadoDevolucion,
                'movimiento_devolucion_id' => $movimiento->id,
            ]);

            $asignacion->ordenTrabajo?->registrarEvento(
                'herramienta_devuelta',
                sprintf('Herramienta «%s» devuelta (%s).', $asignacion->inventario->nombre, str($estadoDevolucion)->replace('_', ' ')),
                $actor,
            );

            return $asignacion->fresh();
        });
    }
}
