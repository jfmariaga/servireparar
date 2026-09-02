<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de una solicitud de despacho (spec 003, US6). Cada línea es de
 * `inventario` (ítem del catálogo, descuenta stock al entregar) o de
 * `compra_externa` (el almacén no lo tiene: se registra proveedor/costo/motivo
 * sin crear ítem, ni lote, ni movimiento de inventario — FR-021).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_solicitud_despacho', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes_despacho')->cascadeOnDelete();
            $table->enum('origen', ['inventario', 'compra_externa']);
            $table->foreignId('inventario_id')->nullable()->constrained('inventario');
            $table->string('descripcion', 200)->nullable();
            $table->decimal('cantidad', 12, 2);
            $table->decimal('costo_unitario', 12, 2)->nullable();
            $table->string('proveedor_externo', 150)->nullable();
            $table->decimal('costo_compra_externa', 12, 2)->nullable();
            $table->string('motivo')->nullable();
            $table->foreignId('movimiento_id')->nullable()->constrained('movimientos_inventario');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_solicitud_despacho');
    }
};
