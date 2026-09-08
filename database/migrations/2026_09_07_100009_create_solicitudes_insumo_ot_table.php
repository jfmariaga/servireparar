<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitud de insumo generada automáticamente por una tarea de OT (spec 002,
 * FR-003; spec 003 US1 — el Almacenista la atiende). Es el punto de integración
 * OT → Bodega: la crea SolicitudInsumoService al guardar una tarea con insumo;
 * el descuento de stock real ocurre cuando el Almacenista la entrega (spec 003),
 * quedando enlazada a su `movimiento_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_insumo_ot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ot_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->foreignId('detalle_ot_id')->constrained('detalle_ot')->cascadeOnDelete();
            $table->foreignId('inventario_id')->constrained('inventario');
            $table->decimal('cantidad', 10, 2);
            $table->enum('estado', ['pendiente', 'aprobada', 'entregada', 'rechazada'])->default('pendiente')->index();
            $table->foreignId('solicitada_por')->nullable()->constrained('users');
            $table->foreignId('movimiento_id')->nullable()->constrained('movimientos_inventario')->nullOnDelete();
            $table->string('motivo_rechazo', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_insumo_ot');
    }
};
