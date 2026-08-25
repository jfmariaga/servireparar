<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reemplaza el campo de texto libre `unidad_medida` por una FK al catálogo
     * `unidades_medida`, evitando variantes del mismo valor por error de digitación
     * (ej. "galon" vs "Galón" vs "GAL").
     */
    public function up(): void
    {
        Schema::table('inventario', function (Blueprint $table) {
            $table->dropColumn('unidad_medida');
        });

        Schema::table('inventario', function (Blueprint $table) {
            $table->foreignId('unidad_medida_id')->nullable()->after('categoria_id')->constrained('unidades_medida');
        });
    }

    public function down(): void
    {
        Schema::table('inventario', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unidad_medida_id');
        });

        Schema::table('inventario', function (Blueprint $table) {
            $table->string('unidad_medida', 20)->nullable()->after('categoria_id');
        });
    }
};
