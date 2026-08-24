<?php

namespace Database\Seeders;

use App\Enums\RolPrioridad;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Crea los 4 roles fijos del sistema (spec 001) y permisos base por módulo.
     * Los permisos son deliberadamente amplios en esta etapa inicial; se afinan
     * módulo por módulo a medida que se implementan (spec 000, 002, 003, ...).
     */
    public function run(): void
    {
        foreach (RolPrioridad::ordenados() as $nombreRol) {
            Role::firstOrCreate(['name' => $nombreRol, 'guard_name' => 'web']);
        }

        $permisos = [
            // Catálogos maestros (spec 000)
            'manage-clientes',
            'manage-proveedores',
            'manage-contratistas',
            // Usuarios (spec 001)
            'manage-usuarios',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        /** @var Role $administrador */
        $administrador = Role::findByName(RolPrioridad::Administrador->value);
        $administrador->syncPermissions($permisos);

        /** @var Role $jefeDeTaller */
        $jefeDeTaller = Role::findByName(RolPrioridad::JefeDeTaller->value);
        $jefeDeTaller->syncPermissions(['manage-clientes', 'manage-proveedores', 'manage-contratistas']);
    }
}
