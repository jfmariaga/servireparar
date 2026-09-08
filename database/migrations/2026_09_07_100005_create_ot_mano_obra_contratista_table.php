<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mano de obra de contratistas externos por OT (spec 002, FR-015). Réplica del
 * bloque "Mano de Obra Contratista" del formato real de OT: contratista (spec
 * 000), especialidad, cantidad y valor. Suma directa al costo total de la OT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ot_mano_obra_contratista', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ot_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->foreignId('contratista_id')->constrained('contratistas');
            $table->string('especialidad', 100)->nullable();
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->decimal('valor', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ot_mano_obra_contratista');
    }
};
