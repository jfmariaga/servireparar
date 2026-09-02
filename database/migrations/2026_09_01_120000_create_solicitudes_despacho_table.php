<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canal de venta mostrador sin OT (spec 003, US6). El Vendedor crea la solicitud;
 * el Almacenista la hace avanzar por sus estados. El descuento de stock ocurre
 * únicamente al confirmar la entrega firmada (ver DespachoService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_despacho', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 12)->unique();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('vendedor_id')->constrained('users');
            $table->enum('estado', ['borrador', 'solicitada', 'recibida', 'remisionada', 'entregada', 'anulada'])
                ->default('solicitada')->index();
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_solicitud');
            $table->foreignId('recibida_por')->nullable()->constrained('users');
            $table->timestamp('recibida_en')->nullable();
            $table->foreignId('remisionada_por')->nullable()->constrained('users');
            $table->timestamp('remisionada_en')->nullable();
            $table->timestamp('entregada_en')->nullable();
            $table->foreignId('anulada_por')->nullable()->constrained('users');
            $table->string('motivo_anulacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_despacho');
    }
};
