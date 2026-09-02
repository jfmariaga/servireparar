<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos que la remisión física del taller (formato "REMISIÓN BAQ Nº ####") tiene
 * y que faltaban en la versión digital (spec 003, US6): nombre de quien entrega
 * físicamente, nota libre de entrega, y marca de envío de la copia PDF al correo
 * del cliente tras recibir a satisfacción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remisiones_entrega', function (Blueprint $table) {
            $table->string('entregado_por_nombre', 150)->nullable()->after('generada_por');
            $table->text('nota_entrega')->nullable()->after('firma');
            $table->timestamp('enviada_al_cliente_en')->nullable()->after('entregada_en');
        });
    }

    public function down(): void
    {
        Schema::table('remisiones_entrega', function (Blueprint $table) {
            $table->dropColumn(['entregado_por_nombre', 'nota_entrega', 'enviada_al_cliente_en']);
        });
    }
};
