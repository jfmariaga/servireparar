<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anti-spam del aviso de vencimiento (Phase 11 / H23): `ot:revisar-vencimientos`
 * solo dispara una vez por OT hasta que se resuelva o se reinicie el marcador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->timestamp('alertado_vencimiento_en')->nullable()->after('fecha_entrega');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropColumn('alertado_vencimiento_en');
        });
    }
};
