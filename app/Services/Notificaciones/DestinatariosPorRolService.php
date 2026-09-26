<?php

namespace App\Services\Notificaciones;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resuelve a quién avisar por rol, evitando duplicados cuando un usuario tiene
 * más de un rol aplicable (spec 008, FR-002). Único punto de esta regla —
 * `NotificadorOt`, `NotificarEventosEquipos` y `FirmaFisicaPendienteService` lo
 * reutilizan en vez de repetir el `->unique('id')` cada uno por su lado.
 */
class DestinatariosPorRolService
{
    /**
     * @param  array<int, string>  $roles
     * @param  array<int, ?User>  $ademasDe  usuarios puntuales a incluir además de los roles (p. ej. quien creó la OT)
     * @return Collection<int, User>
     */
    public function resolver(array $roles, array $ademasDe = []): Collection
    {
        return User::query()
            ->where('estado', 'activo')
            ->role($roles)
            ->get()
            ->concat(collect($ademasDe)->filter())
            ->unique('id')
            ->values();
    }
}
