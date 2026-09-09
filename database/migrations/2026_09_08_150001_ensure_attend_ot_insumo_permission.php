<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11 / H18: garantiza el permiso `attend-ot-insumo` (atender solicitudes de
 * insumo de OT en Bodega) y su asignación a Almacenista y Administrador, para que
 * en entornos con datos previos baste `php artisan migrate` — sin re-correr
 * `RolesSeeder`. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permId = DB::table('permissions')->where('name', 'attend-ot-insumo')->where('guard_name', 'web')->value('id');

        if (! $permId) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => 'attend-ot-insumo',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['Almacenista', 'Administrador'] as $rol) {
            $roleId = DB::table('roles')->where('name', $rol)->where('guard_name', 'web')->value('id');

            if ($roleId) {
                DB::table('role_has_permissions')->updateOrInsert(
                    ['permission_id' => $permId, 'role_id' => $roleId],
                    [],
                );
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permId = DB::table('permissions')->where('name', 'attend-ot-insumo')->where('guard_name', 'web')->value('id');

        if ($permId) {
            DB::table('role_has_permissions')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
