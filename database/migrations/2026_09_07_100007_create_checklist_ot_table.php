<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checklist de cierre de una OT (spec 002, `CHECKLIST_OT`, FR-007). Bloquea el
 * paso a "Finalizada" mientras algún ítem siga sin responder (`cumple` null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_ot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ot_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->string('item', 255);
            $table->boolean('cumple')->nullable();
            $table->string('observaciones', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_ot');
    }
};
