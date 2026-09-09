<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada solicitud de insumo pasa a colgar de una línea concreta
 * (`detalle_ot_insumos`), no de la tarea entera (Phase 11 / D6). Añade también el
 * estado `cancelada` para dejar traza cuando una línea de insumo se quita de la
 * tarea sin borrar la solicitud en silencio (H11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_insumo_ot', function (Blueprint $table) {
            // nullOnDelete: si se quita la línea, la solicitud (ya cancelada) queda
            // como rastro histórico ligada aún a la tarea por `detalle_ot_id` (H11/H22).
            $table->foreignId('detalle_ot_insumo_id')->nullable()->after('detalle_ot_id')
                ->constrained('detalle_ot_insumos')->nullOnDelete();
        });

        // Enlaza las solicitudes existentes con su línea (match por tarea + ítem).
        DB::table('solicitudes_insumo_ot')->orderBy('id')->each(function ($solicitud) {
            $lineaId = DB::table('detalle_ot_insumos')
                ->where('detalle_ot_id', $solicitud->detalle_ot_id)
                ->where('inventario_id', $solicitud->inventario_id)
                ->value('id');

            if ($lineaId) {
                DB::table('solicitudes_insumo_ot')->where('id', $solicitud->id)->update(['detalle_ot_insumo_id' => $lineaId]);
            }
        });

        DB::statement("ALTER TABLE solicitudes_insumo_ot MODIFY estado ENUM('pendiente','aprobada','entregada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        DB::table('solicitudes_insumo_ot')->where('estado', 'cancelada')->update(['estado' => 'rechazada']);
        DB::statement("ALTER TABLE solicitudes_insumo_ot MODIFY estado ENUM('pendiente','aprobada','entregada','rechazada') NOT NULL DEFAULT 'pendiente'");

        Schema::table('solicitudes_insumo_ot', function (Blueprint $table) {
            $table->dropConstrainedForeignId('detalle_ot_insumo_id');
        });
    }
};
