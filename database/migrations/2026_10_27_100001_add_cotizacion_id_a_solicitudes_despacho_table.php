<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaza una solicitud de despacho con la cotización aceptada que la originó
 * (botón "Solicitar despacho" en cotizaciones/gestionar). Nullable: los
 * despachos de mostrador creados sin cotización de por medio siguen igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_despacho', function (Blueprint $table) {
            $table->foreignId('cotizacion_id')->nullable()->after('cliente_id')->constrained('cotizaciones');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_despacho', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cotizacion_id');
        });
    }
};
