<?php

namespace App\Services\OrdenTrabajo;

use App\Models\EstadoOt;
use App\Models\OrdenTrabajo;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Máquina de estados de la OT (spec 002, FR-004). ÚNICO punto del sistema que
 * escribe `ordenes_trabajo.estado_id`. El estado se deriva del avance agregado
 * de las tareas; nunca se edita a mano desde un formulario.
 *
 * Progresión: en_revision → (pendiente) → en_curso → finalizada → entregada.
 * - Sin tareas iniciadas: en_revision (o pendiente si el Jefe de Taller la planificó).
 * - Alguna tarea en curso/finalizada, pero no todas finalizadas: en_curso.
 * - Todas las tareas finalizadas + checklist resuelto: finalizada.
 * - Todas finalizadas pero checklist incompleto: se queda en en_curso (FR-007 / SC-003).
 * - entregada es terminal y solo la fija confirmarEntrega().
 */
class EstadoOtService
{
    /** Estado con el que nace toda OT. */
    public function estadoInicialId(): int
    {
        return EstadoOt::idPorSlug(EstadoOt::EN_REVISION);
    }

    /**
     * Recalcula y persiste el estado de la OT a partir de sus tareas.
     * No hace nada si la OT ya está en un estado terminal.
     */
    public function recalcular(OrdenTrabajo $ot, ?User $actor = null): OrdenTrabajo
    {
        $ot->loadMissing('estado', 'tareas', 'checklist');

        if (optional($ot->estado)->es_terminal) {
            return $ot;
        }

        $nuevoSlug = $this->slugDerivado($ot);

        return $this->transicionar($ot, $nuevoSlug, $actor, 'Transición automática por avance de tareas');
    }

    /** ¿La OT puede cerrarse (pasar a Finalizada)? */
    public function puedeFinalizar(OrdenTrabajo $ot): bool
    {
        $ot->loadMissing('tareas', 'checklist');

        return $ot->tareasActivasFinalizadas() && $ot->checklistCompleto();
    }

    /** El Jefe de Taller marca la OT como planificada/lista para ejecutar. */
    public function planificar(OrdenTrabajo $ot, ?User $actor = null): OrdenTrabajo
    {
        if (! $ot->estaEnEstado(EstadoOt::EN_REVISION)) {
            throw ValidationException::withMessages([
                'estado' => 'Solo una OT en revisión puede pasar a pendiente.',
            ]);
        }

        return $this->transicionar($ot, EstadoOt::PENDIENTE, $actor, 'OT planificada por el Jefe de Taller');
    }

    /**
     * Reabre una OT finalizada a "En curso" (rechazo de salida de equipo, FR-013,
     * o corrección administrativa que agrega trabajo).
     */
    public function reabrir(OrdenTrabajo $ot, ?User $actor = null, string $motivo = 'OT reabierta'): OrdenTrabajo
    {
        if (optional($ot->estado)->es_terminal) {
            throw ValidationException::withMessages([
                'estado' => 'Una OT entregada no puede reabrirse.',
            ]);
        }

        return $this->transicionar($ot, EstadoOt::EN_CURSO, $actor, $motivo);
    }

    /** Marca la OT como entregada (estado terminal). Llamado por el flujo de salida de equipo. */
    public function confirmarEntrega(OrdenTrabajo $ot, ?User $actor = null): OrdenTrabajo
    {
        $ot->loadMissing('estado');

        if (! $ot->estaEnEstado(EstadoOt::FINALIZADA)) {
            throw ValidationException::withMessages([
                'estado' => 'Solo una OT finalizada puede marcarse como entregada.',
            ]);
        }

        $ot->fecha_entrega = now();

        return $this->transicionar($ot, EstadoOt::ENTREGADA, $actor, 'Entrega confirmada al cliente');
    }

    /** Cancela la OT (estado terminal). Solo Administrador / Jefe de Taller, con motivo (D8). */
    public function cancelar(OrdenTrabajo $ot, ?User $actor, string $motivo): OrdenTrabajo
    {
        $ot->loadMissing('estado');

        if (optional($ot->estado)->es_terminal) {
            throw ValidationException::withMessages([
                'estado' => 'Una OT '.optional($ot->estado)->nombre.' no se puede cancelar.',
            ]);
        }

        return $this->transicionar($ot, EstadoOt::CANCELADA, $actor, "OT cancelada. Motivo: {$motivo}");
    }

    private function slugDerivado(OrdenTrabajo $ot): string
    {
        // Las tareas canceladas no cuentan para derivar el estado de la OT.
        $tareas = $ot->tareas->where('estado_tarea', '!=', 'cancelada');

        if ($tareas->isEmpty()) {
            return EstadoOt::EN_REVISION;
        }

        $todasFinalizadas = $tareas->every(fn ($t) => $t->estado_tarea === 'finalizada');

        if ($todasFinalizadas && $ot->checklistCompleto()) {
            return EstadoOt::FINALIZADA;
        }

        $algoIniciado = $tareas->contains(fn ($t) => in_array($t->estado_tarea, ['en_curso', 'finalizada'], true));

        if ($algoIniciado) {
            return EstadoOt::EN_CURSO;
        }

        // Ninguna tarea iniciada: conserva "pendiente" si ya se planificó, si no "en revisión".
        return $ot->estaEnEstado(EstadoOt::PENDIENTE) ? EstadoOt::PENDIENTE : EstadoOt::EN_REVISION;
    }

    private function transicionar(OrdenTrabajo $ot, string $slug, ?User $actor, string $descripcion): OrdenTrabajo
    {
        $ot->loadMissing('estado');
        $anterior = optional($ot->estado)->slug;

        if ($anterior === $slug) {
            $ot->save(); // persiste cambios colaterales (ej. fecha_entrega) sin registrar evento redundante
            $ot->load('estado');

            return $ot;
        }

        if ($slug === EstadoOt::FINALIZADA) {
            $ot->fecha_finalizacion = now();
        }

        // Si la OT sale de "finalizada" hacia ejecución, una salida de equipo ya
        // aprobada queda invalidada: el equipo no puede salir hasta re-aprobarla (H7).
        $salidaInvalidada = $anterior === EstadoOt::FINALIZADA
            && ! in_array($slug, [EstadoOt::FINALIZADA, EstadoOt::ENTREGADA, EstadoOt::CANCELADA], true)
            && $ot->salida_estado === 'aprobada';

        if ($salidaInvalidada) {
            $ot->forceFill([
                'salida_estado' => 'no_solicitada',
                'salida_solicitada_por' => null,
                'salida_solicitada_en' => null,
                'salida_resuelta_por' => null,
                'salida_resuelta_en' => null,
                'salida_motivo_rechazo' => null,
            ]);
        }

        $ot->estado_id = EstadoOt::idPorSlug($slug);
        $ot->save();
        $ot->load('estado');

        $ot->registrarEvento(
            'cambio_estado',
            sprintf('%s: %s → %s', $descripcion, $anterior ?? '—', $slug),
            $actor,
        );

        if ($salidaInvalidada) {
            $ot->registrarEvento('correccion', 'Salida de equipo (aprobada) invalidada: la OT volvió a ejecución.', $actor);
        }

        return $ot;
    }
}
