<?php

namespace App\Policies;

use App\Enums\RolPrioridad;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-usuarios');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-usuarios');
    }

    public function update(User $user): bool
    {
        return $user->can('manage-usuarios');
    }

    /**
     * Impide inactivar/eliminar al último Administrador activo (spec 001, FR-008).
     */
    public function deactivate(User $user, User $target): bool
    {
        if (! $user->can('manage-usuarios')) {
            return false;
        }

        if ($target->hasRole(RolPrioridad::Administrador->value)) {
            $administradoresActivos = User::role(RolPrioridad::Administrador->value)
                ->where('estado', 'activo')
                ->where('id', '!=', $target->id)
                ->count();

            return $administradoresActivos > 0;
        }

        return true;
    }
}
