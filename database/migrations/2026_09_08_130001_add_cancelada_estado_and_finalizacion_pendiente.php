<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11 / D3 + D8:
 *  - estado `cancelada` para la OT (terminal) y para la tarea.
 *  - `detalle_ot.finalizacion_solicitada_en`: el técnico marca la tarea "lista
 *    para finalizar" y, si tiene insumos sin entregar, la finalización real la
 *    confirma el Jefe de Taller.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('estados_ot')->updateOrInsert(
            ['slug' => 'cancelada'],
            ['nombre' => 'Cancelada', 'orden' => 9, 'es_terminal' => true, 'updated_at' => now(), 'created_at' => now()],
        );

        DB::statement("ALTER TABLE detalle_ot MODIFY estado_tarea ENUM('pendiente','en_curso','finalizada','cancelada') NOT NULL DEFAULT 'pendiente'");

        Schema::table('detalle_ot', function (Blueprint $table) {
            $table->timestamp('finalizacion_solicitada_en')->nullable()->after('fecha_fin');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_ot', function (Blueprint $table) {
            $table->dropColumn('finalizacion_solicitada_en');
        });

        DB::table('detalle_ot')->where('estado_tarea', 'cancelada')->update(['estado_tarea' => 'pendiente']);
        DB::statement("ALTER TABLE detalle_ot MODIFY estado_tarea ENUM('pendiente','en_curso','finalizada') NOT NULL DEFAULT 'pendiente'");

        DB::table('ordenes_trabajo')
            ->whereIn('estado_id', DB::table('estados_ot')->where('slug', 'cancelada')->pluck('id'))
            ->update(['estado_id' => DB::table('estados_ot')->where('slug', 'en_curso')->value('id')]);
        DB::table('estados_ot')->where('slug', 'cancelada')->delete();
    }
};
