<?php

namespace Database\Seeders;

use App\Enums\RolPrioridad;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Crea los 5 roles fijos del sistema (spec 001; Vendedor añadido 2026-09-01
     * para el canal de venta sin OT del spec 003, US6) y permisos base por módulo.
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
            // Personal / técnicos (spec 004)
            'manage-tecnicos',
            // Órdenes de Trabajo (spec 002)
            'manage-ot',
            'execute-ot',
            // Bodega atiende las solicitudes de insumo de OT (spec 002, Phase 11 / H18)
            'attend-ot-insumo',
            // Equipos (spec 005)
            'manage-equipos',
            // Inventario / Bodega (spec 003)
            'manage-inventario',
            'approve-auditorias-inventario',
            // Despachos / venta mostrador sin OT (spec 003, US6)
            'manage-despachos',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        /** @var Role $administrador */
        $administrador = Role::findByName(RolPrioridad::Administrador->value);
        $administrador->syncPermissions($permisos);

        /** @var Role $jefeDeTaller */
        $jefeDeTaller = Role::findByName(RolPrioridad::JefeDeTaller->value);
        $jefeDeTaller->syncPermissions(['manage-clientes', 'manage-proveedores', 'manage-contratistas', 'manage-equipos', 'manage-ot', 'execute-ot']);

        /** @var Role $almacenista */
        $almacenista = Role::findByName(RolPrioridad::Almacenista->value);
        $almacenista->syncPermissions(['manage-inventario', 'manage-despachos', 'attend-ot-insumo']);

        /** @var Role $vendedor */
        $vendedor = Role::findByName(RolPrioridad::Vendedor->value);
        $vendedor->syncPermissions(['manage-despachos']);

        /** @var Role $tecnico */
        $tecnico = Role::findByName(RolPrioridad::Tecnico->value);
        $tecnico->syncPermissions(['execute-ot']);
    }
}
