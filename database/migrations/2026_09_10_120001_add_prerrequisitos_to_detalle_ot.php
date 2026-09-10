<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12 / D10 (H25): prerrequisitos entre tareas de una misma OT.
 *  - `detalle_ot.orden`: posición de la tarea en la lista (drag & drop / reorden).
 *    Se puebla con el orden de creación actual (id ascendente por OT).
 *  - `detalle_ot_prerrequisitos`: pivote auto-referenciado. Una tarea
 *    (`detalle_ot_id`) no puede iniciarse hasta finalizar todas sus
 *    `prerrequisito_id`. Sin ciclos (se valida en el servicio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_ot', function (Blueprint $table) {
            $table->unsignedInteger('orden')->default(0)->after('descripcion');
        });

        // Poblar `orden` con el orden de creación actual, por OT.
        foreach (DB::table('detalle_ot')->select('ot_id')->distinct()->pluck('ot_id') as $otId) {
            $pos = 1;
            foreach (DB::table('detalle_ot')->where('ot_id', $otId)->orderBy('id')->pluck('id') as $id) {
                DB::table('detalle_ot')->where('id', $id)->update(['orden' => $pos++]);
            }
        }

        Schema::create('detalle_ot_prerrequisitos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detalle_ot_id')->constrained('detalle_ot')->cascadeOnDelete();
            $table->foreignId('prerrequisito_id')->constrained('detalle_ot')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['detalle_ot_id', 'prerrequisito_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_ot_prerrequisitos');

        Schema::table('detalle_ot', function (Blueprint $table) {
            $table->dropColumn('orden');
        });
    }
};
