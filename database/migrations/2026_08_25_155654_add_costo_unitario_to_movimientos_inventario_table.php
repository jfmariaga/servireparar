<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registra el costo unitario vigente en el momento de cada movimiento —
     * necesario para costear correctamente OT (spec 002) con el costo real de
     * cuando se consumió el insumo, no el costo actual del maestro (que puede
     * haber cambiado desde entonces).
     */
    public function up(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->decimal('costo_unitario', 12, 2)->nullable()->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->dropColumn('costo_unitario');
        });
    }
};
