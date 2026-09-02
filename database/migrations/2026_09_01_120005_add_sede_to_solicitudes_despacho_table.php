<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ciudad de la remisión (spec 003, US6): las remisiones se hacen por ciudad,
 * identificadas con la sigla aeroportuaria (IATA) — ej. "REMISIÓN BAQ" para
 * Barranquilla. Se elige al crear la solicitud (ver config/despachos.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_despacho', function (Blueprint $table) {
            $table->string('sede', 3)->default(config('despachos.sede_por_defecto', 'BAQ'))->after('vendedor_id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_despacho', function (Blueprint $table) {
            $table->dropColumn('sede');
        });
    }
};
