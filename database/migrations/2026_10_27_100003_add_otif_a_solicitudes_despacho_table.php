<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_despacho', function (Blueprint $table) {
            $table->date('fecha_promesa_entrega')->nullable()->after('fecha_solicitud');
            $table->boolean('entrega_completa')->nullable()->after('entregada_en');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_despacho', function (Blueprint $table) {
            $table->dropColumn(['fecha_promesa_entrega', 'entrega_completa']);
        });
    }
};
