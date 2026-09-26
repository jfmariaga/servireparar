<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caso comercial de cotización (spec 006), independiente de `ordenes_trabajo`
 * porque su ciclo de vida (recepción por correo, envío, aceptación,
 * facturación) es distinto al de una OT operativa. `correo_original_referencia`
 * guarda el Message-ID del correo que originó el caso — junto con
 * `mensajes_cotizacion.message_id_correo`, mantiene el hilo de integración
 * aunque el cliente remitente no se haya identificado automáticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->foreignId('equipo_id')->nullable()->constrained('equipos');
            $table->enum('estado', ['en_revision', 'cotizada', 'aceptada', 'rechazada', 'entregada', 'facturada'])
                ->default('en_revision');
            $table->string('correo_original_referencia')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
