<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_id')->constrained('inventario');
            $table->enum('tipo_mov', ['entrada', 'salida', 'devolucion']);
            $table->decimal('cantidad', 12, 2);
            $table->timestamp('fecha');
            $table->string('motivo')->nullable();
            $table->string('referencia')->nullable();
            $table->foreignId('usuario_id')->constrained('users');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores');
            $table->enum('origen', ['ot', 'manual', 'entrada_proveedor', 'devolucion']);
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
