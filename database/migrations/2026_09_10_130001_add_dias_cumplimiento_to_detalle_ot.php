<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13 / D17: plazo de cumplimiento por tarea (en días). La suma de los
 * plazos de las tareas activas de una OT no puede superar su `tiempo_estimado_dias`.
 * Una tarea sin finalizar cuyo plazo ya venció (desde su inicio, o desde que se
 * liberó la OT) se muestra "atrasada".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_ot', function (Blueprint $table) {
            $table->decimal('dias_cumplimiento', 6, 2)->nullable()->after('orden');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_ot', function (Blueprint $table) {
            $table->dropColumn('dias_cumplimiento');
        });
    }
};
