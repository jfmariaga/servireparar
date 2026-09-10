<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12 / D14 (H28): la solicitud de insumo registra a qué técnico se entrega
 * = el encargado de la tarea (`detalle_ot.tecnico_id`). Bodega no puede cambiarlo,
 * solo lo muestra. Backfill de las solicitudes existentes desde su tarea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_insumo_ot', function (Blueprint $table) {
            $table->foreignId('entregado_a_tecnico_id')->nullable()->after('inventario_id')
                ->constrained('tecnicos')->nullOnDelete();
        });

        DB::statement('
            UPDATE solicitudes_insumo_ot s
            JOIN detalle_ot d ON d.id = s.detalle_ot_id
            SET s.entregado_a_tecnico_id = d.tecnico_id
        ');
    }

    public function down(): void
    {
        Schema::table('solicitudes_insumo_ot', function (Blueprint $table) {
            $table->dropForeign(['entregado_a_tecnico_id']);
            $table->dropColumn('entregado_a_tecnico_id');
        });
    }
};
