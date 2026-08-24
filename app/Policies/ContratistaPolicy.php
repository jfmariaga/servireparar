<?php

namespace App\Policies;

use App\Models\User;

class ContratistaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-contratistas');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-contratistas');
    }

    public function update(User $user): bool
    {
        return $user->can('manage-contratistas');
    }
}
