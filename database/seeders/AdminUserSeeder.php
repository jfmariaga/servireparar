<?php

namespace Database\Seeders;

use App\Enums\RolPrioridad;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea el primer Administrador del sistema (bootstrap).
     * Credenciales de desarrollo — cambiar antes de producción.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@servireparar.com'],
            [
                'name' => 'Administrador SERVIOPS',
                'password' => 'password',
                'estado' => 'activo',
            ]
        );

        if (! $admin->hasRole(RolPrioridad::Administrador->value)) {
            $admin->assignRole(RolPrioridad::Administrador->value);
        }
    }
}
