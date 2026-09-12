<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía `ot_eventos.tipo` con `insumo_reactivado`: una solicitud que Bodega
 * había rechazado y el Jefe de Taller vuelve a pedir desde la tarea (con o sin
 * cambios) — antes quedaba en "pendiente" sin dejar traza ni avisar a Bodega.
 */
return new class extends Migration
{
    private string $con = "'creacion','cambio_estado','correccion','salida_solicitada','salida_aprobada','salida_rechazada','entrega','evidencia','insumo_solicitado','insumo_entregado','insumo_rechazado','insumo_modificado','insumo_cancelado','insumo_reactivado','herramienta_asignada','herramienta_devuelta'";

    private string $sin = "'creacion','cambio_estado','correccion','salida_solicitada','salida_aprobada','salida_rechazada','entrega','evidencia','insumo_solicitado','insumo_entregado','insumo_rechazado','insumo_modificado','insumo_cancelado','herramienta_asignada','herramienta_devuelta'";

    public function up(): void
    {
        DB::statement("ALTER TABLE ot_eventos MODIFY tipo ENUM({$this->con}) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ot_eventos MODIFY tipo ENUM({$this->sin}) NOT NULL");
    }
};
