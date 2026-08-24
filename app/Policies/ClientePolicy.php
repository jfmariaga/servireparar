<?php

namespace App\Policies;

use App\Models\User;

class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-clientes');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-clientes');
    }

    public function update(User $user): bool
    {
        return $user->can('manage-clientes');
    }
}
