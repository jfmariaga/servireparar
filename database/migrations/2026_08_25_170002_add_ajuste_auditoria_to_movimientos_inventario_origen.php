<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Un sobrante detectado en auditoría (spec 003, FR-008a) se registra como un
     * lote nuevo de tipo "entrada" para que quede disponible al costeo FIFO — su
     * origen propio evita que se confunda con una compra real a proveedor.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE movimientos_inventario MODIFY origen ENUM('ot','manual','entrada_proveedor','devolucion','ajuste_auditoria') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE movimientos_inventario MODIFY origen ENUM('ot','manual','entrada_proveedor','devolucion') NOT NULL");
    }
};
