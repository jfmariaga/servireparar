<?php

namespace App\Policies;

use App\Models\Compra;
use App\Models\User;

class CompraPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-compras');
    }

    public function view(User $user, Compra $compra): bool
    {
        return $user->can('manage-compras');
    }

    public function manage(User $user): bool
    {
        return $user->can('manage-compras');
    }
}
