<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos laborales del técnico para la hoja de vida (spec 004, FR-012) y
 * eliminación de `tarifa_hora`: el costeo pasa a día y el valor día se deriva
 * del sueldo historizado (`sueldos_tecnico`), no de una tarifa manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tecnicos', function (Blueprint $table) {
            $table->date('fecha_ingreso')->nullable()->after('especialidad_id');
            $table->string('cargo', 120)->nullable()->after('fecha_ingreso');
            $table->enum('tipo_contrato', ['termino_fijo', 'indefinido', 'prestacion_servicios'])
                ->nullable()->after('cargo');
            $table->dropColumn('tarifa_hora');
        });
    }

    public function down(): void
    {
        Schema::table('tecnicos', function (Blueprint $table) {
            $table->decimal('tarifa_hora', 10, 2)->nullable()->after('especialidad_id');
            $table->dropColumn(['fecha_ingreso', 'cargo', 'tipo_contrato']);
        });
    }
};
