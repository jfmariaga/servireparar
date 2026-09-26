<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Envío con mensajero: la solicitud puede salir de bodega con la firma de
 * quien entrega solamente (el stock se descuenta en ese momento), quedar en
 * un estado intermedio `despachada` mientras el mensajero consigue la firma
 * física del cliente, y cerrarse después con esa evidencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE solicitudes_despacho MODIFY estado ENUM('borrador', 'solicitada', 'recibida', 'remisionada', 'despachada', 'entregada', 'anulada') NOT NULL DEFAULT 'solicitada'");

        Schema::table('solicitudes_despacho', function (Blueprint $table) {
            $table->string('mensajero_nombre', 150)->nullable()->after('remisionada_en');
            $table->foreignId('despachada_por')->nullable()->after('mensajero_nombre')->constrained('users');
            $table->timestamp('despachada_en')->nullable()->after('despachada_por');
            $table->timestamp('alertado_firma_pendiente_en')->nullable()->after('entregada_en');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_despacho', function (Blueprint $table) {
            $table->dropConstrainedForeignId('despachada_por');
            $table->dropColumn(['mensajero_nombre', 'despachada_en', 'alertado_firma_pendiente_en']);
        });

        DB::statement("ALTER TABLE solicitudes_despacho MODIFY estado ENUM('borrador', 'solicitada', 'recibida', 'remisionada', 'entregada', 'anulada') NOT NULL DEFAULT 'solicitada'");
    }
};
