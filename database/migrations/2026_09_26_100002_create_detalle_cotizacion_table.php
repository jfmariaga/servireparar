<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de una Cotización (spec 006) — de `servicio` (maestra `servicios`) o
 * de `insumo` (reutiliza `inventario`, spec 003), nunca ambos a la vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_cotizacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->enum('tipo_item', ['servicio', 'insumo']);
            $table->foreignId('servicio_id')->nullable()->constrained('servicios');
            $table->foreignId('inventario_id')->nullable()->constrained('inventario');
            $table->decimal('cantidad', 12, 2);
            $table->decimal('costo_unitario', 12, 2);
            $table->decimal('valor_total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_cotizacion');
    }
};
