<?php

namespace App\Policies;

use App\Models\SolicitudDespacho;
use App\Models\User;

/**
 * Autorización del canal de venta mostrador sin OT (spec 003, US6).
 *
 * - Vendedor y Administrador: crear, editar y anular solicitudes.
 * - Almacenista y Administrador: recibir, remisionar y entregar.
 * - Todos los anteriores: ver.
 *
 * El permiso subyacente es `manage-despachos` (spec 001, RolesSeeder);
 * la separación fina se hace por rol.
 */
class SolicitudDespachoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-despachos');
    }

    public function view(User $user, SolicitudDespacho $solicitud): bool
    {
        return $user->can('manage-despachos');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Vendedor', 'Administrador']);
    }

    public function update(User $user, SolicitudDespacho $solicitud): bool
    {
        return $solicitud->estado === 'solicitada'
            && $user->hasAnyRole(['Vendedor', 'Administrador']);
    }

    public function anular(User $user, SolicitudDespacho $solicitud): bool
    {
        return $solicitud->puedeAnularse()
            && $user->hasAnyRole(['Vendedor', 'Almacenista', 'Administrador']);
    }

    public function gestionarAlmacen(User $user, SolicitudDespacho $solicitud): bool
    {
        return $user->hasAnyRole(['Almacenista', 'Administrador']);
    }
}
