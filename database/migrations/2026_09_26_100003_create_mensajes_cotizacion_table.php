<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hilo de comunicación de una Cotización (spec 006): cada entrada es un
 * correo entrante/saliente o un comentario interno. `message_id_correo`
 * matchea respuestas del cliente en el mismo hilo vía `In-Reply-To`/
 * `References` (US3), independiente de si la Cotización tiene cliente
 * identificado automáticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes_cotizacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->enum('autor_tipo', ['administrador', 'cliente', 'sistema']);
            $table->foreignId('autor_id')->nullable()->constrained('users');
            $table->text('contenido');
            $table->string('adjunto_url')->nullable();
            $table->string('message_id_correo')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes_cotizacion');
    }
};
