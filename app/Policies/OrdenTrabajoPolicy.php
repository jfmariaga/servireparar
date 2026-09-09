<?php

namespace App\Policies;

use App\Enums\RolPrioridad;
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
        return $user->canAny(['manage-ot', 'execute-ot']);
    }

    public function view(User $user, OrdenTrabajo $ot): bool
    {
        return $user->canAny(['manage-ot', 'execute-ot']);
    }

    public function create(User $user): bool
    {
        return $user->can('manage-ot');
    }

    public function update(User $user, OrdenTrabajo $ot): bool
    {
        return $user->can('manage-ot') && ! $ot->estaBloqueada();
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
