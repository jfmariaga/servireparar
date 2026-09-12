<?php

namespace App\Policies;

use App\Enums\RolPrioridad;
use App\Models\EstadoOt;
use App\Models\OrdenTrabajo;
use App\Models\User;

/**
 * Autorización del módulo de Órdenes de Trabajo (spec 002).
 *
 * - Administrador y Jefe de Taller: crear y corregir OT (`manage-ot`).
 * - Técnico: ejecutar tareas asignadas (`execute-ot`), sin crear ni corregir.
 * - Aprobar/rechazar la salida de equipo: SOLO Administrador (FR-013).
 * - Ver el costeo y la utilidad neta: SOLO Administrador (User Story 5).
 */
class OrdenTrabajoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['manage-ot', 'execute-ot', 'attend-ot-insumo']);
    }

    public function view(User $user, OrdenTrabajo $ot): bool
    {
        // Gestión (Admin/Jefe) y Bodega (solo lectura, para dar contexto a lo que
        // entrega desde la cola de insumos/préstamos — Phase 13).
        if ($user->canAny(['manage-ot', 'attend-ot-insumo'])) {
            return true;
        }

        // Técnico: solo las OT donde tiene alguna tarea asignada (Phase 11 / D7).
        return $user->can('execute-ot')
            && $user->tecnico
            && $ot->tareas()->where('tecnico_id', $user->tecnico->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('manage-ot');
    }

    public function update(User $user, OrdenTrabajo $ot): bool
    {
        return $user->can('manage-ot') && ! $ot->estaBloqueada();
    }

    /**
     * Cancelar la OT: Administrador siempre puede (si no está bloqueada); el
     * Jefe de Taller ya no puede una vez la OT se liberó y alguna tarea tiene
     * trabajo realizado (en curso o finalizada) — evita perder avance ya hecho.
     */
    public function cancel(User $user, OrdenTrabajo $ot): bool
    {
        if (! $user->can('manage-ot') || $ot->estaBloqueada()) {
            return false;
        }

        if ($user->hasRole(RolPrioridad::Administrador->value)) {
            return true;
        }

        $yaLiberada = ! $ot->estaEnEstado(EstadoOt::EN_REVISION);
        $tieneTrabajoRealizado = $ot->tareas()->whereIn('estado_tarea', [EstadoOt::EN_CURSO, EstadoOt::FINALIZADA])->exists();

        return ! ($yaLiberada && $tieneTrabajoRealizado);
    }

    public function executeTareas(User $user, OrdenTrabajo $ot): bool
    {
        return $user->can('execute-ot')
            && ! $ot->estaBloqueada()
            && in_array(optional($ot->estado)->slug, ['pendiente', 'en_curso'], true);
    }

    public function requestEquipmentExit(User $user, OrdenTrabajo $ot): bool
    {
        return $user->can('manage-ot');
    }

    public function approveEquipmentExit(User $user, OrdenTrabajo $ot): bool
    {
        return $user->hasRole(RolPrioridad::Administrador->value);
    }

    public function viewCosteo(User $user, OrdenTrabajo $ot): bool
    {
        return $user->hasRole(RolPrioridad::Administrador->value);
    }

    /** Editar el costeo (contratistas, valor del proyecto): Admin y solo si la OT no está congelada. */
    public function manageCosteo(User $user, OrdenTrabajo $ot): bool
    {
        return $user->hasRole(RolPrioridad::Administrador->value) && ! $ot->estaBloqueada();
    }
}
