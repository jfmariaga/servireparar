<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Herramientas de inventario asignadas a una OT (spec 002, Phase 11 / D4, H9).
 * Una herramienta se asigna (sale del almacén como `en_uso`) y debe devolverse
 * antes de solicitar la salida del equipo. Cada movimiento (salida / devolución)
 * queda enlazado para trazabilidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ot_herramientas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ot_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->foreignId('inventario_id')->constrained('inventario');
            $table->foreignId('asignada_por')->nullable()->constrained('users');
            $table->timestamp('asignada_en');
            $table->foreignId('movimiento_salida_id')->nullable()->constrained('movimientos_inventario')->nullOnDelete();
            $table->timestamp('devuelta_en')->nullable();
            $table->enum('estado_devolucion', ['disponible', 'dañada', 'en_mantenimiento'])->nullable();
            $table->foreignId('movimiento_devolucion_id')->nullable()->constrained('movimientos_inventario')->nullOnDelete();
            $table->timestamps();

            $table->index(['ot_id', 'devuelta_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ot_herramientas');
    }
};
