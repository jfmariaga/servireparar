<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 150);
            $table->enum('tipo', ['herramienta', 'consumible']);
            $table->foreignId('categoria_id')->constrained('categorias_inventario');
            $table->string('ubicacion', 20)->nullable();
            $table->string('codigo_barras', 50)->nullable();
            $table->string('unidad_medida', 20)->nullable();
            $table->decimal('stock_actual', 12, 2)->default(0);
            $table->decimal('stock_minimo', 12, 2)->default(0);
            $table->decimal('costo_unitario', 12, 2)->nullable();
            $table->enum('estado_herramienta', ['disponible', 'en_uso', 'dañada', 'en_mantenimiento'])->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('categoria_id');
            $table->index('ubicacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario');
    }
};
