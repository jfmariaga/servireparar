<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('detalle_compra');
        Schema::dropIfExists('compras');
    }

    public function down(): void
    {
        // Módulo de Compras retirado del sistema; no se restaura el esquema original.
    }
};
