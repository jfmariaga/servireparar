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
                'direccion_servicio' => $datos['direccion_servicio'] ?? null,
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

            $this->asegurarSumaPlazos(
                (float) ($datos['tiempo_estimado_dias'] ?? 0),
                array_sum(array_map(fn ($t) => $this->plazoDe($t) ?? 0, $tareas)),
                filled($datos['tiempo_estimado_dias'] ?? null),
            );

            $porUid = [];
            $orden = 1;
            foreach ($tareas as $tarea) {
                $detalle = $ot->tareas()->create([
                    'descripcion' => $tarea['descripcion'],
                    'orden' => $orden++,
                    'dias_cumplimiento' => $this->plazoDe($tarea),
                    'tecnico_id' => $tarea['tecnico_id'],
                    'estado_tarea' => 'pendiente',
                ]);

                if (filled($tarea['uid'] ?? null)) {
                    $porUid[(string) $tarea['uid']] = $detalle;
                }

                $this->insumos->aplicarLineasInsumo($detalle, $this->lineasInsumo($tarea), $actor);
            }

            // Prerrequisitos entre tareas del mismo lote (referidos por su `uid`, D10).
            foreach ($tareas as $tarea) {
                if (! filled($tarea['uid'] ?? null) || ($tarea['prerrequisitos'] ?? []) === []) {
                    continue;
                }

                $detalle = $porUid[(string) $tarea['uid']];
                $ids = collect($tarea['prerrequisitos'])
                    ->map(fn ($uid) => $porUid[(string) $uid]?->id ?? null)
                    ->filter(fn ($id) => $id !== null && $id !== $detalle->id)
                    ->unique()->values()->all();

                $detalle->prerrequisitos()->sync($ids);
            }

            $this->asegurarSinCiclos($ot->fresh());

            foreach ((array) config('ot.checklist_por_defecto', []) as $item) {
                $ot->checklist()->create(['item' => $item]);
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
            'prioridad_id', 'descripcion', 'tipo_servicio', 'direccion_servicio', 'tiempo_estimado_dias',
            'valor_proyecto', 'equipo_estado_ingreso', 'observaciones',
        ]);

        // D17: no se puede bajar el estimado por debajo de la suma de plazos ya asignados.
        if (array_key_exists('tiempo_estimado_dias', $permitidos) && filled($permitidos['tiempo_estimado_dias'])) {
            $this->asegurarSumaPlazos((float) $permitidos['tiempo_estimado_dias'], $ot->diasCumplimientoAsignados(), true);
        }

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

        $ot->loadMissing('tareas');
        $this->asegurarSumaPlazos(
            (float) $ot->tiempo_estimado_dias,
            $ot->diasCumplimientoAsignados() + ($this->plazoDe($tarea) ?? 0),
            $ot->tiempo_estimado_dias !== null,
        );

        return DB::transaction(function () use ($ot, $actor, $tarea) {
            $detalle = $ot->tareas()->create([
                'descripcion' => $tarea['descripcion'],
                'orden' => (int) $ot->tareas()->max('orden') + 1,
                'dias_cumplimiento' => $this->plazoDe($tarea),
                'tecnico_id' => $tarea['tecnico_id'],
                'estado_tarea' => 'pendiente',
            ]);

            $this->insumos->aplicarLineasInsumo($detalle, $this->lineasInsumo($tarea), $actor);
            $this->sincronizarPrerrequisitos($detalle, $tarea['prerrequisitos'] ?? null);
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
        // D18: solo se puede editar una tarea que aún no se ha iniciado.
        if ($tarea->estado_tarea !== 'pendiente') {
            throw ValidationException::withMessages(['tarea' => 'Solo se puede editar una tarea que aún no se ha iniciado.']);
        }

        $ot = $tarea->ordenTrabajo;
        if (array_key_exists('dias_cumplimiento', $datos) && $ot?->tiempo_estimado_dias !== null) {
            $this->asegurarSumaPlazos(
                (float) $ot->tiempo_estimado_dias,
                $ot->diasCumplimientoAsignados(excluyendoTareaId: $tarea->id) + ($this->plazoDe($datos) ?? 0),
                true,
            );
        }

        return DB::transaction(function () use ($tarea, $actor, $datos) {
            $tecAntes = $tarea->tecnico?->usuario?->name ?? 'técnico #'.$tarea->tecnico_id;

            $tarea->update([
                'descripcion' => $datos['descripcion'] ?? $tarea->descripcion,
                'tecnico_id' => $datos['tecnico_id'] ?? $tarea->tecnico_id,
                'dias_cumplimiento' => array_key_exists('dias_cumplimiento', $datos)
                    ? $this->plazoDe($datos)
                    : $tarea->dias_cumplimiento,
            ]);

            $this->insumos->aplicarLineasInsumo($tarea, $this->lineasInsumo($datos), $actor);

            if (array_key_exists('prerrequisitos', $datos)) {
                $this->sincronizarPrerrequisitos($tarea, $datos['prerrequisitos']);
            }

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
            ->where('estado', 'entregada')
            ->exists();

        if ($procesada) {
            throw ValidationException::withMessages(['tarea' => 'Una solicitud de insumo de esta tarea ya fue procesada por Bodega.']);
        }

        DB::transaction(function () use ($ot, $tarea, $actor) {
            $desc = $tarea->descripcion;
            $this->liberarDependientes($tarea, $actor);
            $tarea->prerrequisitos()->detach();
            $tarea->delete();
            $ot->registrarEvento('correccion', "Tarea eliminada: «{$desc}».", $actor);
            $this->estados->recalcular($ot->fresh(), $actor);
        });
    }

    /**
     * El técnico marca la tarea lista para finalizar (D3). Los días trabajados
     * los calcula el sistema desde la fecha de inicio (Phase 13); si se pasa un
     * valor explícito (tests / correcciones), se respeta. Si todos sus insumos ya
     * fueron entregados por Bodega, se finaliza directo; si no, queda a la espera
     * de la confirmación del Jefe de Taller.
     */
    public function marcarTareaListaParaFinalizar(DetalleOt $tarea, User $actor, ?float $diasTrabajados = null): DetalleOt
    {
        if ($tarea->estado_tarea !== 'en_curso') {
            throw ValidationException::withMessages(['tarea' => 'La tarea debe estar en curso para finalizarla.']);
        }

        if (! $tarea->tieneEvidenciaImagen()) {
            throw ValidationException::withMessages(['tarea' => 'Sube una imagen de evidencia de la tarea antes de finalizarla.']);
        }

        $diasTrabajados ??= $this->diasTrabajadosAuto($tarea);

        return DB::transaction(function () use ($tarea, $actor, $diasTrabajados) {
            if ($tarea->insumosPendientesDeEntrega()) {
                $tarea->update([
                    'dias_trabajados' => $diasTrabajados,
                    'finalizacion_solicitada_en' => now(),
                ]);
                $tarea->ordenTrabajo?->registrarEvento(
                    'correccion',
                    sprintf('Tarea «%s» marcada lista para finalizar; espera confirmación del Jefe (insumos sin entregar).', str($tarea->descripcion)->limit(40)),
                    $actor,
                );

                return $tarea->fresh();
            }

            return $this->finalizarTarea($tarea, $actor, $diasTrabajados);
        });
    }

    /** El Jefe de Taller confirma la finalización de una tarea retenida por insumos sin entregar (D3). */
    public function confirmarFinalizacionTarea(DetalleOt $tarea, User $actor): DetalleOt
    {
        if (! $tarea->finalizacionPendiente()) {
            throw ValidationException::withMessages(['tarea' => 'Esta tarea no está a la espera de confirmación.']);
        }

        return $this->finalizarTarea($tarea, $actor, (float) $tarea->dias_trabajados, confirmadaPorJefe: true);
    }

    /**
     * Días trabajados de la tarea calculados por el sistema: días calendario
     * transcurridos desde el inicio, contando el día de inicio (mínimo 1).
     */
    private function diasTrabajadosAuto(DetalleOt $tarea): float
    {
        $inicio = ($tarea->fecha_inicio ?? $tarea->created_at ?? now())->copy()->startOfDay();

        return (float) max(1, $inicio->diffInDays(now()->startOfDay()) + 1);
    }

    private function finalizarTarea(DetalleOt $tarea, User $actor, float $dias, bool $confirmadaPorJefe = false): DetalleOt
    {
        return DB::transaction(function () use ($tarea, $actor, $dias, $confirmadaPorJefe) {
            $tarea->update([
                'estado_tarea' => 'finalizada',
                'fecha_fin' => now(),
                'dias_trabajados' => $dias,
                'finalizacion_solicitada_en' => null,
            ]);

            if ($confirmadaPorJefe) {
                $tarea->ordenTrabajo?->registrarEvento(
                    'correccion',
                    sprintf('El Jefe de Taller confirmó la finalización de la tarea «%s».', str($tarea->descripcion)->limit(40)),
                    $actor,
                );
            }

            $this->estados->recalcular($tarea->ordenTrabajo->fresh(), $actor);

            return $tarea->fresh();
        });
    }

    /**
     * Cancela una tarea no finalizada (D8). Libera las solicitudes de insumo
     * pendientes; las ya entregadas quedan como costo real. La OT debe conservar
     * al menos una tarea no cancelada.
     */
    public function cancelarTarea(DetalleOt $tarea, User $actor, string $motivo): DetalleOt
    {
        $ot = $tarea->ordenTrabajo;

        if (in_array($tarea->estado_tarea, ['finalizada', 'cancelada'], true)) {
            throw ValidationException::withMessages(['tarea' => 'Solo se puede cancelar una tarea pendiente o en curso.']);
        }

        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'Indica el motivo de la cancelación.']);
        }

        if ($ot->tareas()->where('estado_tarea', '!=', 'cancelada')->count() <= 1) {
            throw ValidationException::withMessages(['tarea' => 'La OT debe conservar al menos una tarea activa. Cancela la OT completa si aplica.']);
        }

        return DB::transaction(function () use ($ot, $tarea, $actor, $motivo) {
            $this->liberarInsumosPendientes($tarea, $actor);
            $this->liberarDependientes($tarea, $actor);

            $tarea->update(['estado_tarea' => 'cancelada', 'finalizacion_solicitada_en' => null]);
            $ot->registrarEvento('correccion', sprintf('Tarea «%s» cancelada. Motivo: %s', str($tarea->descripcion)->limit(40), $motivo), $actor);
            $this->estados->recalcular($ot->fresh(), $actor);

            return $tarea->fresh();
        });
    }

    /**
     * Cancela la OT completa (D8): estado terminal `cancelada`. Libera todas las
     * reservas de insumo pendientes. Las herramientas en préstamo son ajenas al
     * ciclo de la OT y no la bloquean (Phase 12 / D15).
     */
    public function cancelarOt(OrdenTrabajo $ot, User $actor, string $motivo): OrdenTrabajo
    {
        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'Indica el motivo de la cancelación.']);
        }

        return DB::transaction(function () use ($ot, $actor, $motivo) {
            foreach ($ot->tareas()->whereNot('estado_tarea', 'finalizada')->get() as $tarea) {
                $this->liberarInsumosPendientes($tarea, $actor);
            }

            $this->estados->cancelar($ot->fresh(['estado']), $actor, $motivo);

            return $ot->fresh(['estado']);
        });
    }

    private function liberarInsumosPendientes(DetalleOt $tarea, User $actor): void
    {
        $tarea->loadMissing('solicitudesInsumo.inventario');

        foreach ($tarea->solicitudesInsumo->where('estado', 'pendiente') as $solicitud) {
            $solicitud->update(['estado' => 'cancelada']);
            $tarea->ordenTrabajo?->registrarEvento(
                'insumo_cancelado',
                sprintf('Solicitud de %s uds. de %s liberada al cancelar la tarea «%s».',
                    rtrim(rtrim(number_format((float) $solicitud->cantidad, 2), '0'), '.'),
                    $solicitud->inventario?->nombre ?? 'ítem',
                    str((string) $tarea->descripcion)->limit(40),
                ),
                $actor,
            );
        }
    }

    /**
     * Deja los prerrequisitos de la tarea igual a `$ids` (ids de otras tareas de
     * la MISMA OT). Rechaza auto-dependencias, ids ajenos a la OT y ciclos (D10).
     *
     * @param  array<int, int|string>|null  $ids  null = no tocar
     */
    private function sincronizarPrerrequisitos(DetalleOt $tarea, ?array $ids): void
    {
        if ($ids === null) {
            return;
        }

        $tarea->loadMissing('ordenTrabajo');
        $ot = $tarea->ordenTrabajo;

        $idsOt = $ot->tareas()->pluck('id')->all();

        $limpios = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== $tarea->id)
            ->unique()
            ->values();

        $ajenos = $limpios->reject(fn ($id) => in_array($id, $idsOt, true));
        if ($ajenos->isNotEmpty()) {
            throw ValidationException::withMessages([
                'prerrequisitos' => 'Una tarea solo puede depender de otras tareas de la misma OT.',
            ]);
        }

        $tarea->prerrequisitos()->sync($limpios->all());

        $this->asegurarSinCiclos($ot->fresh());
    }

    /**
     * DFS de detección de ciclos sobre el grafo de prerrequisitos de la OT
     * (arista tarea → prerrequisito). Si hay un ciclo, revierte con error (D10).
     */
    private function asegurarSinCiclos(OrdenTrabajo $ot): void
    {
        $aristas = DB::table('detalle_ot_prerrequisitos')
            ->join('detalle_ot', 'detalle_ot.id', '=', 'detalle_ot_prerrequisitos.detalle_ot_id')
            ->where('detalle_ot.ot_id', $ot->id)
            ->get(['detalle_ot_prerrequisitos.detalle_ot_id', 'detalle_ot_prerrequisitos.prerrequisito_id']);

        $ady = [];
        foreach ($aristas as $a) {
            $ady[$a->detalle_ot_id][] = $a->prerrequisito_id;
        }

        $color = []; // 1 = en pila, 2 = terminado
        $tieneCiclo = function (int $nodo) use (&$tieneCiclo, &$ady, &$color): bool {
            $color[$nodo] = 1;
            foreach ($ady[$nodo] ?? [] as $siguiente) {
                if (($color[$siguiente] ?? 0) === 1) {
                    return true;
                }
                if (($color[$siguiente] ?? 0) === 0 && $tieneCiclo($siguiente)) {
                    return true;
                }
            }
            $color[$nodo] = 2;

            return false;
        };

        foreach (array_keys($ady) as $nodo) {
            if (($color[$nodo] ?? 0) === 0 && $tieneCiclo($nodo)) {
                throw ValidationException::withMessages([
                    'prerrequisitos' => 'Los prerrequisitos forman una dependencia circular entre tareas.',
                ]);
            }
        }
    }

    /**
     * Al cancelar o eliminar una tarea, sus dependientes dejan de requerirla
     * (conservan los demás prerrequisitos) y queda traza por cada una (D10).
     */
    private function liberarDependientes(DetalleOt $tarea, User $actor): void
    {
        $tarea->loadMissing('dependientes', 'ordenTrabajo');

        foreach ($tarea->dependientes as $dependiente) {
            $dependiente->prerrequisitos()->detach($tarea->id);
            $tarea->ordenTrabajo?->registrarEvento(
                'correccion',
                sprintf('La tarea «%s» ya no depende de «%s» (prerrequisito retirado).',
                    str((string) $dependiente->descripcion)->limit(40),
                    str((string) $tarea->descripcion)->limit(40),
                ),
                $actor,
            );
        }
    }

    /** Plazo de cumplimiento declarado para una tarea (días), o null. */
    private function plazoDe(array $tarea): ?float
    {
        $v = $tarea['dias_cumplimiento'] ?? null;

        return filled($v) && (float) $v > 0 ? round((float) $v, 2) : null;
    }

    /**
     * La suma de los plazos de las tareas activas no puede superar el tiempo
     * estimado de la OT (Phase 13 / D17). Solo se valida si hay estimado.
     */
    private function asegurarSumaPlazos(float $estimado, float $sumaPlazos, bool $hayEstimado): void
    {
        if (! $hayEstimado || $estimado <= 0) {
            return;
        }

        if (round($sumaPlazos, 2) > round($estimado, 2)) {
            throw ValidationException::withMessages([
                'dias_cumplimiento' => sprintf(
                    'La suma de los plazos de las tareas (%s día(s)) supera el tiempo estimado de la OT (%s día(s)).',
                    rtrim(rtrim(number_format($sumaPlazos, 2), '0'), '.'),
                    rtrim(rtrim(number_format($estimado, 2), '0'), '.'),
                ),
            ]);
        }
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
