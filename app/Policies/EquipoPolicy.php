<?php

namespace App\Policies;

use App\Models\User;

class EquipoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-equipos');
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
