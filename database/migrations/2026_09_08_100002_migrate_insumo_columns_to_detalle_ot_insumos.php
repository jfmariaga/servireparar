<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Traslada los insumos que vivían en `detalle_ot.insumo_id` / `cantidad_insumo`
 * a la nueva tabla `detalle_ot_insumos` (una fila por tarea con insumo) y elimina
 * esas columnas para que quede una única fuente de verdad (Phase 11 / D6).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('detalle_ot')
            ->whereNotNull('insumo_id')
            ->where('cantidad_insumo', '>', 0)
            ->orderBy('id')
            ->each(function ($tarea) {
                DB::table('detalle_ot_insumos')->insertOrIgnore([
                    'detalle_ot_id' => $tarea->id,
                    'inventario_id' => $tarea->insumo_id,
                    'cantidad' => $tarea->cantidad_insumo,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('detalle_ot', function (Blueprint $table) {
            $table->dropConstrainedForeignId('insumo_id');
            $table->dropColumn('cantidad_insumo');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_ot', function (Blueprint $table) {
            $table->foreignId('insumo_id')->nullable()->after('tecnico_id')->constrained('inventario')->nullOnDelete();
            $table->decimal('cantidad_insumo', 10, 2)->nullable()->after('insumo_id');
        });

        // Restaura la primera línea de insumo de cada tarea en las columnas viejas.
        foreach (DB::table('detalle_ot_insumos')->orderBy('id')->get()->groupBy('detalle_ot_id') as $detalleId => $lineas) {
            $primera = $lineas->first();
            DB::table('detalle_ot')->where('id', $detalleId)->update([
                'insumo_id' => $primera->inventario_id,
                'cantidad_insumo' => $primera->cantidad,
            ]);
        }
    }
};
