<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Por línea de insumo, si sale del almacén (`inventario`, descuenta stock al
 * generar el despacho) o es compra externa (`compra_externa`, no descuenta
 * nada — el control interno queda en el hilo de comunicación de la
 * cotización, nunca en el documento). No aplica a líneas de servicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_cotizacion', function (Blueprint $table) {
            $table->enum('origen', ['inventario', 'compra_externa'])->nullable()->after('inventario_id');
        });

        DB::table('detalle_cotizacion')->where('tipo_item', 'insumo')->update(['origen' => 'inventario']);
    }

    public function down(): void
    {
        Schema::table('detalle_cotizacion', function (Blueprint $table) {
            $table->dropColumn('origen');
        });
    }
};
