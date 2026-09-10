<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12 / D15-D16 (H29): `ot_herramientas` deja de ser "asignación de
 * herramienta a una OT por el Jefe" y pasa a ser un **préstamo que pide el
 * técnico**. El vínculo duro es con el técnico; la OT/tarea es solo contexto.
 *
 *   estado: solicitada → entregada → devuelta   (o solicitada → rechazada)
 *
 * La devolución la registra el Almacenista. Reutiliza la tabla y sus columnas de
 * movimiento/estado de devolución de Phase 11.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Nuevas columnas (nullable para poder rellenar filas existentes).
        Schema::table('ot_herramientas', function (Blueprint $table) {
            $table->foreignId('tecnico_id')->nullable()->after('ot_id')->constrained('tecnicos');
            $table->foreignId('detalle_ot_id')->nullable()->after('tecnico_id')->constrained('detalle_ot')->nullOnDelete();
            $table->enum('estado', ['solicitada', 'entregada', 'rechazada', 'devuelta'])->default('solicitada')->after('detalle_ot_id');
            $table->timestamp('solicitada_en')->nullable()->after('estado');
            $table->foreignId('entregada_por')->nullable()->after('movimiento_salida_id')->constrained('users')->nullOnDelete();
            $table->foreignId('recibida_por')->nullable()->after('movimiento_devolucion_id')->constrained('users')->nullOnDelete();
            $table->string('motivo_rechazo')->nullable()->after('recibida_por');
        });

        // 2. Rellenar desde los datos de Phase 11 (asignaciones = ya entregadas).
        DB::statement('
            UPDATE ot_herramientas h
            LEFT JOIN (
                SELECT ot_id, MIN(tecnico_id) AS tecnico_id
                FROM detalle_ot GROUP BY ot_id
            ) d ON d.ot_id = h.ot_id
            SET h.tecnico_id = d.tecnico_id,
                h.solicitada_en = h.asignada_en,
                h.entregada_por = h.asignada_por,
                h.estado = CASE WHEN h.devuelta_en IS NULL THEN \'entregada\' ELSE \'devuelta\' END
        ');

        DB::table('ot_herramientas')->whereNull('tecnico_id')->delete();

        // 3. Endurecer: tecnico_id NOT NULL, ot_id NULLABLE (la OT es solo contexto).
        DB::statement('ALTER TABLE ot_herramientas MODIFY tecnico_id BIGINT UNSIGNED NOT NULL');

        Schema::table('ot_herramientas', function (Blueprint $table) {
            $table->dropForeign(['ot_id']);
        });
        DB::statement('ALTER TABLE ot_herramientas MODIFY ot_id BIGINT UNSIGNED NULL');
        Schema::table('ot_herramientas', function (Blueprint $table) {
            $table->foreign('ot_id')->references('id')->on('ordenes_trabajo')->nullOnDelete();
        });

        // 4. Quitar las columnas de "asignación por el Jefe".
        Schema::table('ot_herramientas', function (Blueprint $table) {
            $table->dropForeign(['asignada_por']);
            $table->dropColumn(['asignada_por', 'asignada_en']);
        });
    }

    public function down(): void
    {
        Schema::table('ot_herramientas', function (Blueprint $table) {
            $table->foreignId('asignada_por')->nullable()->constrained('users');
            $table->timestamp('asignada_en')->nullable();
        });

        DB::statement('UPDATE ot_herramientas SET asignada_en = COALESCE(solicitada_en, created_at), asignada_por = entregada_por');

        Schema::table('ot_herramientas', function (Blueprint $table) {
            $table->dropForeign(['ot_id']);
        });
        DB::table('ot_herramientas')->whereNull('ot_id')->delete();
        DB::statement('ALTER TABLE ot_herramientas MODIFY ot_id BIGINT UNSIGNED NOT NULL');
        Schema::table('ot_herramientas', function (Blueprint $table) {
            $table->foreign('ot_id')->references('id')->on('ordenes_trabajo')->cascadeOnDelete();

            $table->dropForeign(['tecnico_id']);
            $table->dropForeign(['detalle_ot_id']);
            $table->dropForeign(['entregada_por']);
            $table->dropForeign(['recibida_por']);
            $table->dropColumn(['tecnico_id', 'detalle_ot_id', 'estado', 'solicitada_en', 'entregada_por', 'recibida_por', 'motivo_rechazo']);
        });
    }
};
