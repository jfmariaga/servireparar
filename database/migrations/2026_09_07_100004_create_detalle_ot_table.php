<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tareas de una OT (spec 002, `DETALLE_OT`). Cada tarea tiene un operario
 * (técnico propio) y, opcionalmente, un insumo requerido que dispara una
 * solicitud hacia Bodega (spec 003). El costo de mano de obra propia de la
 * tarea = `dias_trabajados × valor_día del técnico` (spec 004) — no se guarda
 * tarifa en el detalle, se deriva del sueldo historizado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_ot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ot_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->text('descripcion');
            $table->foreignId('tecnico_id')->constrained('tecnicos');
            $table->foreignId('insumo_id')->nullable()->constrained('inventario')->nullOnDelete();
            $table->decimal('cantidad_insumo', 10, 2)->nullable();
            $table->enum('estado_tarea', ['pendiente', 'en_curso', 'finalizada'])->default('pendiente');
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_fin')->nullable();
            $table->decimal('dias_trabajados', 6, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_ot');
    }
};
