<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remisión de entrega (spec 003, US6, FR-023/FR-024): documento imprimible 1:1
 * con la solicitud de despacho, con la firma digital del receptor capturada en
 * pantalla embebida como PNG base64.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remisiones_entrega', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 12)->unique();
            $table->foreignId('solicitud_id')->unique()->constrained('solicitudes_despacho')->cascadeOnDelete();
            $table->foreignId('generada_por')->constrained('users');
            $table->timestamp('fecha');
            // Se rellenan al confirmar la entrega firmada (FR-024); la remisión se
            // crea (con su número) en el paso previo `remisionada`.
            $table->string('recibido_por_nombre', 150)->nullable();
            $table->string('recibido_por_documento', 40)->nullable();
            $table->longText('firma')->nullable();
            $table->timestamp('entregada_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remisiones_entrega');
    }
};
