<?php

namespace App\Policies;

use App\Models\Cotizacion;
use App\Models\User;

class CotizacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-cotizaciones');
    }

    public function view(User $user, Cotizacion $cotizacion): bool
    {
        return $user->can('manage-cotizaciones');
    }

    public function manage(User $user): bool
    {
        return $user->can('manage-cotizaciones');
    }
}
