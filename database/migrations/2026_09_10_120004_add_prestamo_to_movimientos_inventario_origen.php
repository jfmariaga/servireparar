<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 12 / D16: la entrega de una herramienta en préstamo a un técnico genera
 * un movimiento de salida con `origen = 'prestamo'` — distinta de las salidas por
 * OT (`ot`) y por despacho (`despacho`).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE movimientos_inventario MODIFY origen ENUM('ot','manual','entrada_proveedor','devolucion','ajuste_auditoria','despacho','prestamo') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE movimientos_inventario SET origen = 'ot' WHERE origen = 'prestamo'");
        DB::statement("ALTER TABLE movimientos_inventario MODIFY origen ENUM('ot','manual','entrada_proveedor','devolucion','ajuste_auditoria','despacho') NOT NULL");
    }
};
