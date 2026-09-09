<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bodega de un solo paso (Phase 11 / D2): se elimina el estado intermedio
 * `aprobada` de las solicitudes de insumo. Las que estuvieran aprobadas vuelven a
 * `pendiente` (aún no se despacharon). Flujo final: pendiente → entregada | rechazada | cancelada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('solicitudes_insumo_ot')->where('estado', 'aprobada')->update(['estado' => 'pendiente']);
        DB::statement("ALTER TABLE solicitudes_insumo_ot MODIFY estado ENUM('pendiente','entregada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE solicitudes_insumo_ot MODIFY estado ENUM('pendiente','aprobada','entregada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente'");
    }
};
