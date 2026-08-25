<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite cancelar una auditoría abierta por error, sin dejarla indefinidamente
     * "en curso" ni forzarla a cerrarse como si hubiera concluido normalmente.
     */
    public function up(): void
    {
        Schema::table('auditorias_inventario', function (Blueprint $table) {
            $table->enum('estado', ['abierta', 'cerrada', 'cancelada'])->default('abierta')->after('iniciada_por');
        });

        // Backfill: auditorías ya cerradas (fecha_cierre no nula) quedan como 'cerrada'.
        DB::table('auditorias_inventario')->whereNotNull('fecha_cierre')->update(['estado' => 'cerrada']);
    }

    public function down(): void
    {
        Schema::table('auditorias_inventario', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
