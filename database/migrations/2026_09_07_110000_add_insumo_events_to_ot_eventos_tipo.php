<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía el enum `ot_eventos.tipo` con los eventos de la atención de insumos por
 * Bodega (spec 003 US1): entrega y rechazo de la solicitud generada por una tarea.
 */
return new class extends Migration
{
    private string $con = "'creacion','cambio_estado','correccion','salida_solicitada','salida_aprobada','salida_rechazada','entrega','evidencia','insumo_solicitado','insumo_entregado','insumo_rechazado'";

    private string $sin = "'creacion','cambio_estado','correccion','salida_solicitada','salida_aprobada','salida_rechazada','entrega','evidencia','insumo_solicitado'";

    public function up(): void
    {
        DB::statement("ALTER TABLE ot_eventos MODIFY tipo ENUM({$this->con}) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ot_eventos MODIFY tipo ENUM({$this->sin}) NOT NULL");
    }
};
