<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firma de quien entrega físicamente (spec 003, US6, FR-026): la remisión del
 * taller lleva firma tanto de quien recibe como de quien entrega. `firma`
 * (existente) es la del receptor; `firma_entrega` es la del despachador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remisiones_entrega', function (Blueprint $table) {
            $table->longText('firma_entrega')->nullable()->after('firma');
        });
    }

    public function down(): void
    {
        Schema::table('remisiones_entrega', function (Blueprint $table) {
            $table->dropColumn('firma_entrega');
        });
    }
};
