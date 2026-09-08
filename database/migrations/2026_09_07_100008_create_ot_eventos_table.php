<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de trazabilidad de una OT (spec 002, constitución principio IV):
 * cambios de estado, correcciones administrativas, solicitudes y rechazos de
 * salida de equipo. Solo se inserta, nunca se sobrescribe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ot_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ot_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users');
            $table->enum('tipo', ['creacion', 'cambio_estado', 'correccion', 'salida_solicitada', 'salida_aprobada', 'salida_rechazada', 'entrega', 'evidencia', 'insumo_solicitado']);
            $table->text('descripcion');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ot_eventos');
    }
};
