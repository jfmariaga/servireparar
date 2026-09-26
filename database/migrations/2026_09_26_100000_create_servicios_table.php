<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestra de servicios (spec 006) — análoga a `inventario` pero para mano de
 * obra/servicios cotizables, sin stock ni movimientos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->decimal('costo_unitario', 12, 2);
            $table->string('unidad_medida', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicios');
    }
};
