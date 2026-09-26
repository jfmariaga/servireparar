<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ítems comprados en una Compra a proveedor (spec 006, US4). Reutiliza
 * `inventario` como maestra de insumos, igual que `detalle_cotizacion`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('inventario_id')->constrained('inventario');
            $table->decimal('cantidad', 12, 2);
            $table->decimal('costo_unitario', 12, 2);
            $table->decimal('valor_total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_compra');
    }
};
