<?php

namespace Tests\Feature\Despacho;

use App\Enums\RolPrioridad;
use App\Models\User;

/**
 * Helpers compartidos por los tests del canal de venta sin OT (spec 003, US6).
 */
trait DespachoTestHelpers
{
    protected function vendedor(): User
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole(RolPrioridad::Vendedor->value);

        return $user;
    }

    protected function almacenista(): User
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole(RolPrioridad::Almacenista->value);

        return $user;
    }

    protected function firmaDummy(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }
}
