<?php

namespace App\Policies;

use App\Models\User;

class AuditoriaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-inventario');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-inventario');
    }

    /**
     * Aprobar/rechazar un ajuste de auditoría — doble validación, solo Administrador
     * (spec 003, FR-008a).
     */
    public function approve(User $user): bool
    {
        return $user->can('approve-auditorias-inventario');
    }
}
