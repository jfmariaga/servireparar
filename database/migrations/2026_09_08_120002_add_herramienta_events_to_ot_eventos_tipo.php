<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía `ot_eventos.tipo` con los eventos de asignación y devolución de
 * herramientas de inventario en una OT (Phase 11 / D4).
 */
return new class extends Migration
{
    private string $con = "'creacion','cambio_estado','correccion','salida_solicitada','salida_aprobada','salida_rechazada','entrega','evidencia','insumo_solicitado','insumo_entregado','insumo_rechazado','insumo_modificado','insumo_cancelado','herramienta_asignada','herramienta_devuelta'";

    private string $sin = "'creacion','cambio_estado','correccion','salida_solicitada','salida_aprobada','salida_rechazada','entrega','evidencia','insumo_solicitado','insumo_entregado','insumo_rechazado','insumo_modificado','insumo_cancelado'";

    public function up(): void
    {
        DB::statement("ALTER TABLE ot_eventos MODIFY tipo ENUM({$this->con}) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ot_eventos MODIFY tipo ENUM({$this->sin}) NOT NULL");
    }
};
