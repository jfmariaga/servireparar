<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de estados del ciclo de vida de una OT (spec 002, `ESTADOS_OT`, FR-004).
 * El campo `estado_id` de `ordenes_trabajo` solo lo escribe EstadoOtService; este
 * catálogo se siembra desde EstadosOtSeeder y no se edita en UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados_ot', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 30)->unique();
            $table->string('nombre', 50);
            $table->unsignedTinyInteger('orden')->default(0);
            $table->boolean('es_terminal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estados_ot');
    }
};
