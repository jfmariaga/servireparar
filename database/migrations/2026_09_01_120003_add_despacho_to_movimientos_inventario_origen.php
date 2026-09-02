<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Canal de venta mostrador sin OT (spec 003, US6): cada línea de inventario de
 * una solicitud de despacho genera, al confirmar la entrega firmada, una salida
 * con `origen = 'despacho'` — distinta de las salidas por OT (`ot`) y de las
 * salidas manuales directas previas (`manual`).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE movimientos_inventario MODIFY origen ENUM('ot','manual','entrada_proveedor','devolucion','ajuste_auditoria','despacho') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE movimientos_inventario MODIFY origen ENUM('ot','manual','entrada_proveedor','devolucion','ajuste_auditoria') NOT NULL");
    }
};
