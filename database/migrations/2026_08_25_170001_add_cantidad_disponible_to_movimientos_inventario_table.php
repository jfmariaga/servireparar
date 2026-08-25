<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada movimiento de tipo "entrada" es un lote propio: `cantidad_disponible`
     * arranca igual a `cantidad` y se va descontando a medida que una salida lo
     * consume (FIFO). Así el costo de cada salida se calcula con el costo real de
     * los lotes que efectivamente salieron, no con un costo único promediado o
     * reemplazado del maestro.
     */
    public function up(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->decimal('cantidad_disponible', 12, 2)->nullable()->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->dropColumn('cantidad_disponible');
        });
    }
};
