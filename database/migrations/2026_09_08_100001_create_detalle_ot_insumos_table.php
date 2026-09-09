<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de insumo de una tarea de OT (spec 002, Phase 11 / D6). Reemplaza el par
 * `detalle_ot.insumo_id` + `cantidad_insumo` (una tarea = un insumo) por una tarea
 * con N líneas de insumo. Cada línea genera su propia solicitud hacia Bodega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_ot_insumos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detalle_ot_id')->constrained('detalle_ot')->cascadeOnDelete();
            $table->foreignId('inventario_id')->constrained('inventario');
            $table->decimal('cantidad', 10, 2);
            $table->timestamps();

            $table->unique(['detalle_ot_id', 'inventario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_ot_insumos');
    }
};
