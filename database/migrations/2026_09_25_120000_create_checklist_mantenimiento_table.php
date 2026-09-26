<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checklist técnico digital de mantenimiento (spec 005, US4/FR-007): plantilla
 * única genérica, análoga en mecánica al checklist de cierre de OT (`checklist_ot`,
 * spec 002) pero orientada a la intervención sobre el equipo, no al cierre de la
 * OT. Se precarga por tarea al crear una OT con equipo asociado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ot_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->foreignId('detalle_ot_id')->nullable()->constrained('detalle_ot')->nullOnDelete();
            $table->string('item');
            $table->boolean('cumple')->nullable();
            $table->string('observaciones', 500)->nullable();
            $table->foreignId('respondido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_mantenimiento');
    }
};
