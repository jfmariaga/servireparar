<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitud de compra a un Proveedor (spec 006, US4) — flujo manual paralelo
 * al de Cotizaciones a clientes, mismo patrón de estados pero sin la
 * infraestructura de correo entrante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->enum('estado', ['recepcion', 'cotizacion', 'aprobacion', 'facturada'])->default('recepcion');
            $table->text('observaciones')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->foreignId('creado_por')->constrained('users');
            $table->timestamp('cotizada_en')->nullable();
            $table->timestamp('aprobada_en')->nullable();
            $table->timestamp('facturada_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
