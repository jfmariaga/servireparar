<?php

namespace App\Services\OrdenTrabajo;

use App\Events\OtCreada;
use App\Models\DetalleOt;
use App\Models\OrdenTrabajo;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orquesta la creación y corrección de OT (spec 002, US1/US4). Mantiene los
 * componentes Livewire delgados y concentra las reglas de negocio: al menos
 * una tarea con técnico (FR-002), numeración `OTSV-` (FR-014), generación de
 * solicitudes de insumo (FR-003) y trazabilidad en `ot_eventos` (principio IV).
 */
class OrdenTrabajoService
{
    public function __construct(
        private readonly OtNumberGenerator $numeros = new OtNumberGenerator(),
        private readonly EstadoOtService $estados = new EstadoOtService(),
        private readonly SolicitudInsumoService $insumos = new SolicitudInsumoService(),
    ) {}

    /**
     * @param  array<string, mixed>  $datos     cliente_id, prioridad_id, tipo_servicio, descripcion, tiempo_estimado_dias, valor_proyecto, equipo_* ...
     * @param  array<int, array<string, mixed>>  $tareas  cada una: descripcion, tecnico_id, insumos? (o insumo_id/cantidad_insumo legado)
     */
    public function crear(User $actor, array $datos, array $tareas): OrdenTrabajo
    {
        $tareas = $this->tareasValidas($tareas);

        if ($tareas === []) {
            throw ValidationException::withMessages([
                'tareas' => 'La OT debe tener al menos una tarea con un técnico asignado.',
            ]);
        }

        return DB::transaction(function () use ($actor, $datos, $tareas) {
            $ot = OrdenTrabajo::create([
                'numero_ot' => $this->numeros->siguiente(),
                'cliente_id' => $datos['cliente_id'],
                'equipo_id' => $datos['equipo_id'] ?? null,
                'prioridad_id' => $datos['prioridad_id'],
                'estado_id' => $this->estados->estadoInicialId(),
                'tipo_servicio' => $datos['tipo_servicio'] ?? 'taller',
                'descripcion' => $datos['descripcion'],
                'tiempo_estimado_dias' => $datos['tiempo_estimado_dias'] ?? null,
                'valor_proyecto' => $datos['valor_proyecto'] ?? null,
                'equipo_descripcion' => $datos['equipo_descripcion'] ?? null,
                'equipo_marca' => $datos['equipo_marca'] ?? null,
                'equipo_modelo' => $datos['equipo_modelo'] ?? null,
                'equipo_serie' => $datos['equipo_serie'] ?? null,
                'equipo_estado_ingreso' => $datos['equipo_estado_ingreso'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'creado_por' => $actor->id,
            ]);

            foreach ($tareas as $tarea) {
                $detalle = $ot->tareas()->create([
                    'descripcion' => $tarea['descripcion'],
                    'tecnico_id' => $tarea['tecnico_id'],
                    'estado_tarea' => 'pendiente',
                ]);

                $this->insumos->aplicarLineasInsumo($detalle, $this->lineasInsumo($tarea), $actor);
            }

            $ot->registrarEvento('creacion', 'OT creada con '.count($tareas).' tarea(s).', $actor);

            $ot = $ot->fresh(['estado', 'tareas', 'cliente']);

            OtCreada::dispatch($ot);

            return $ot;
        });
    }

    /**
     * Corrige campos de cabecera de una OT en curso, dejando traza (US4).
     *
     * @param  array<string, mixed>  $cambios
     */
    public function corregir(OrdenTrabajo $ot, User $actor, array $cambios): OrdenTrabajo
    {
        $permitidos = Arr::only($cambios, [
            'prioridad_id', 'descripcion', 'tipo_servicio', 'tiempo_estimado_dias',
            'valor_proyecto', 'equipo_estado_ingreso', 'observaciones',
        ]);

        $antes = Arr::only($ot->getOriginal(), array_keys($permitidos));
        $ot->fill($permitidos)->save();

        $diff = collect($permitidos)
            ->filter(fn ($v, $k) => (string) ($antes[$k] ?? '') !== (string) $v)
            ->keys()
            ->implode(', ');

        if ($diff !== '') {
            $ot->registrarEvento('correccion', "Corrección de: {$diff}.", $actor);
        }

        return $ot->fresh(['estado', 'tareas']);
    }

    /**
     * Agrega una tarea a una OT existente (US4 / FR-009), generando su solicitud
     * de insumo si corresponde y recalculando el estado.
     *
     * @param  array<string, mixed>  $tarea  descripcion, tecnico_id, insumos? (o insumo_id/cantidad_insumo legado)
     */
    public function agregarTarea(OrdenTrabajo $ot, User $actor, array $tarea): DetalleOt
    {
        if (optional($ot->estado)->es_terminal) {
            throw ValidationException::withMessages(['tarea' => 'No se pueden agregar tareas a una OT entregada.']);
        }

        if (blank($tarea['descripcion'] ?? null) || blank($tarea['tecnico_id'] ?? null)) {
            throw ValidationException::withMessages(['tarea' => 'La tarea necesita descripción y técnico.']);
        }

        return DB::transaction(function () use ($ot, $actor, $tarea) {
            $detalle = $ot->tareas()->create([
                'descripcion' => $tarea['descripcion'],
                'tecnico_id' => $tarea['tecnico_id'],
                'estado_tarea' => 'pendiente',
            ]);

            $this->insumos->aplicarLineasInsumo($detalle, $this->lineasInsumo($tarea), $actor);
            $ot->registrarEvento('correccion', "Tarea agregada: «{$detalle->descripcion}».", $actor);
            $this->estados->recalcular($ot->fresh(), $actor);

            return $detalle;
        });
    }

    /**
     * Edita una tarea que aún no está finalizada (descripción, técnico, insumos).
     *
     * @param  array<string, mixed>  $datos  descripcion?, tecnico_id?, insumos? (o insumo_id/cantidad_insumo legado)
     */
    public function actualizarTarea(DetalleOt $tarea, User $actor, array $datos): DetalleOt
    {
        if ($tarea->estado_tarea === 'finalizada') {
            throw ValidationException::withMessages(['tarea' => 'No se puede editar una tarea finalizada.']);
        }

        return DB::transaction(function () use ($tarea, $actor, $datos) {
            $tecAntes = $tarea->tecnico?->usuario?->name ?? 'técnico #'.$tarea->tecnico_id;

            $tarea->update([
                'descripcion' => $datos['descripcion'] ?? $tarea->descripcion,
                'tecnico_id' => $datos['tecnico_id'] ?? $tarea->tecnico_id,
            ]);

            $this->insumos->aplicarLineasInsumo($tarea, $this->lineasInsumo($datos), $actor);
            $tarea = $tarea->fresh(['tecnico.usuario']);

            $tecDespues = $tarea->tecnico?->usuario?->name ?? 'técnico #'.$tarea->tecnico_id;
            $nota = $tecAntes !== $tecDespues
                ? "Tarea «{$tarea->descripcion}» reasignada: {$tecAntes} → {$tecDespues}."
                : "Tarea «{$tarea->descripcion}» editada.";
            $tarea->ordenTrabajo?->registrarEvento('correccion', $nota, $actor);

            return $tarea;
        });
    }

    /**
     * Quita una tarea pendiente de una OT (US4). La OT debe conservar al menos
     * una tarea (FR-002) y no se puede quitar si su insumo ya fue procesado por
     * Bodega.
     */
    public function quitarTarea(DetalleOt $tarea, User $actor): void
    {
        $ot = $tarea->ordenTrabajo;

        if ($ot->tareas()->count() <= 1) {
            throw ValidationException::withMessages(['tarea' => 'La OT debe conservar al menos una tarea con técnico.']);
        }

        if ($tarea->estado_tarea !== 'pendiente') {
            throw ValidationException::withMessages(['tarea' => 'Solo se pueden quitar tareas que aún están pendientes.']);
        }

        $procesada = $tarea->solicitudesInsumo()
            ->whereIn('estado', ['aprobada', 'entregada'])
            ->exists();

        if ($procesada) {
            throw ValidationException::withMessages(['tarea' => 'Una solicitud de insumo de esta tarea ya fue procesada por Bodega.']);
        }

        DB::transaction(function () use ($ot, $tarea, $actor) {
            $desc = $tarea->descripcion;
            $tarea->delete();
            $ot->registrarEvento('correccion', "Tarea eliminada: «{$desc}».", $actor);
            $this->estados->recalcular($ot->fresh(), $actor);
        });
    }

    /**
     * Descarta tareas sin descripción o sin técnico.
     *
     * @param  array<int, array<string, mixed>>  $tareas
     * @return array<int, array<string, mixed>>
     */
    private function tareasValidas(array $tareas): array
    {
        return array_values(array_filter($tareas, fn ($t) => filled($t['descripcion'] ?? null) && filled($t['tecnico_id'] ?? null)));
    }

    /**
     * Normaliza los insumos declarados para una tarea a una lista sin ítems
     * repetidos. Acepta el formato nuevo `insumos => [['inventario_id','cantidad'], …]`
     * y el legado `insumo_id` + `cantidad_insumo` (una sola línea).
     *
     * @param  array<string, mixed>  $tarea
     * @return array<int, array{inventario_id: int, cantidad: float}>
     */
    private function lineasInsumo(array $tarea): array
    {
        $crudas = $tarea['insumos'] ?? [];

        if ($crudas === [] && filled($tarea['insumo_id'] ?? null)) {
            $crudas = [['inventario_id' => $tarea['insumo_id'], 'cantidad' => $tarea['cantidad_insumo'] ?? null]];
        }

        $lineas = [];
        foreach ($crudas as $linea) {
            $invId = filled($linea['inventario_id'] ?? null) ? (int) $linea['inventario_id'] : null;
            $cantidad = (float) ($linea['cantidad'] ?? 0);

            if ($invId !== null && $cantidad > 0) {
                $lineas[$invId] = ['inventario_id' => $invId, 'cantidad' => $cantidad];
            }
        }

        return array_values($lineas);
    }
}
