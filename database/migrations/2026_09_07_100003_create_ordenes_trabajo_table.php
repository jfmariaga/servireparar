<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orden de Trabajo (spec 002, `ORDENES_TRABAJO`). Núcleo operativo del taller.
 * Numeración `OTSV-00001` (FR-014). El estado lo gobierna EstadoOtService a
 * partir del avance de las tareas (FR-004). El sub-flujo de salida de equipo
 * (solicitud → aprobación del Administrador → entrega, FR-008/FR-013) vive en
 * las columnas `salida_*` para no ensuciar la máquina de estados principal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_trabajo', function (Blueprint $table) {
            $table->id();
            $table->string('numero_ot', 20)->unique();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('equipo_id')->nullable()->constrained('equipos')->nullOnDelete();
            $table->foreignId('prioridad_id')->constrained('prioridades');
            $table->foreignId('estado_id')->constrained('estados_ot');
            $table->enum('tipo_servicio', ['taller', 'domicilio'])->default('taller');
            $table->text('descripcion');
            $table->decimal('tiempo_estimado_dias', 6, 2)->nullable();
            $table->decimal('valor_proyecto', 14, 2)->nullable();

            // Datos de recepción del equipo (FR-017) — snapshot libre, sirva o no haya un Equipo formal asociado.
            $table->string('equipo_descripcion', 150)->nullable();
            $table->string('equipo_marca', 100)->nullable();
            $table->string('equipo_modelo', 100)->nullable();
            $table->string('equipo_serie', 100)->nullable();
            $table->string('equipo_estado_ingreso', 255)->nullable();

            // Sub-flujo de salida de equipo (FR-008, FR-013).
            $table->enum('salida_estado', ['no_solicitada', 'solicitada', 'aprobada', 'rechazada'])->default('no_solicitada');
            $table->foreignId('salida_solicitada_por')->nullable()->constrained('users');
            $table->timestamp('salida_solicitada_en')->nullable();
            $table->foreignId('salida_resuelta_por')->nullable()->constrained('users');
            $table->timestamp('salida_resuelta_en')->nullable();
            $table->string('salida_motivo_rechazo', 500)->nullable();
            $table->string('firma_cliente_url', 255)->nullable();

            $table->foreignId('creado_por')->constrained('users');
            $table->timestamp('fecha_finalizacion')->nullable();
            $table->timestamp('fecha_entrega')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['estado_id', 'cliente_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_trabajo');
    }
};
