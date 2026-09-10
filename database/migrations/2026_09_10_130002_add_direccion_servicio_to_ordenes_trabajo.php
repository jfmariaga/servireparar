<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13 / D20: dirección del servicio para OT de tipo `domicilio` (obligatoria
 * en el formulario cuando `tipo_servicio = domicilio`). Los campos de recepción
 * del equipo en taller quedan opcionales para esas OT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->string('direccion_servicio')->nullable()->after('tipo_servicio');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropColumn('direccion_servicio');
        });
    }
};
