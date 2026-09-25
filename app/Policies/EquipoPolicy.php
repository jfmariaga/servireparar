<?php

namespace App\Policies;

use App\Models\Equipo;
use App\Models\User;

class EquipoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-equipos');
    }

    /**
     * Historial técnico del equipo (spec 005, US2): Administrador/Jefe de
     * Taller siempre; Técnico solo si tiene alguna tarea asignada en una OT
     * de ese equipo (mismo criterio que `OrdenTrabajoPolicy::view()`).
     */
    public function view(User $user, Equipo $equipo): bool
    {
        if ($user->can('manage-equipos')) {
            return true;
        }

        return $user->can('execute-ot')
            && $user->tecnico
            && $equipo->ordenesTrabajo()->whereHas('tareas', fn ($q) => $q->where('tecnico_id', $user->tecnico->id))->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('manage-equipos');
    }

    public function update(User $user): bool
    {
        return $user->can('manage-equipos');
    }
}
