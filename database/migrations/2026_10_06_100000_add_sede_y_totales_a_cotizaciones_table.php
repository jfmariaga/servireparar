<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sede (ciudad) de la cotización para el consecutivo `SEDE-####`, descuento de
 * cabecera e IVA a nivel de documento. Las cotizaciones anteriores quedan con
 * sede nula y totales en cero hasta que se recalculen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->string('sede', 3)->nullable()->after('cliente_id');
            $table->decimal('descuento_porcentaje', 5, 2)->default(0)->after('total');
            $table->decimal('subtotal', 12, 2)->default(0)->after('descuento_porcentaje');
            $table->decimal('descuento_valor', 12, 2)->default(0)->after('subtotal');
            $table->decimal('iva_porcentaje', 5, 2)->default(19)->after('descuento_valor');
            $table->decimal('iva_valor', 12, 2)->default(0)->after('iva_porcentaje');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn(['sede', 'descuento_porcentaje', 'subtotal', 'descuento_valor', 'iva_porcentaje', 'iva_valor']);
        });
    }
};
