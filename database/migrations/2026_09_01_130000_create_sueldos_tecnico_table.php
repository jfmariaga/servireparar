<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de sueldos por técnico (spec 004, FR-009): cada cambio de sueldo se
 * guarda como una fila nueva con su fecha de vigencia. El "sueldo vigente" a una
 * fecha es la fila con mayor `vigente_desde` menor o igual a esa fecha. Así, el
 * costeo de mano de obra de una OT cerrada no se altera si luego sube el sueldo
 * (FR-011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sueldos_tecnico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tecnico_id')->constrained('tecnicos')->cascadeOnDelete();
            $table->decimal('sueldo', 12, 2);
            $table->date('vigente_desde');
            $table->foreignId('registrado_por')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['tecnico_id', 'vigente_desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sueldos_tecnico');
    }
};
