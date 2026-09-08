<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evidencias (imágenes/documentos) de una OT (spec 002, `EVIDENCIAS_OT`, FR-006).
 * `tipo_registro` distingue el registro fotográfico de entrada, el de salida y
 * las evidencias de proceso, según el proceso real documentado por el cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidencias_ot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ot_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->foreignId('detalle_ot_id')->nullable()->constrained('detalle_ot')->cascadeOnDelete();
            $table->enum('tipo_registro', ['entrada', 'salida', 'proceso'])->default('proceso');
            $table->string('tipo_archivo', 50)->nullable();
            $table->string('url_archivo', 255);
            $table->string('descripcion', 255)->nullable();
            $table->foreignId('subida_por')->nullable()->constrained('users');
            $table->timestamp('fecha_subida');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidencias_ot');
    }
};
