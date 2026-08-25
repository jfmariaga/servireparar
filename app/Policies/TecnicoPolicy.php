<?php

namespace App\Policies;

use App\Models\User;

class TecnicoPolicy
{
    /**
     * Alta/edición de la ficha de técnico (especialidad, tarifa, activo) — spec 004, FR-001.
     * Se gestiona desde la pantalla de Usuarios (spec 001, FR-012), no en una pantalla propia.
     */
    public function manage(User $user): bool
    {
        return $user->can('manage-tecnicos');
    }
}
