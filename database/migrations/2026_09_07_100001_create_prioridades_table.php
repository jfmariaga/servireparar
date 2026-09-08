<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de niveles de prioridad de una OT (spec 002, `PRIORIDADES`).
 * Fijo — se siembra desde PrioridadesSeeder, sin editor en UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prioridades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->unsignedTinyInteger('nivel')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prioridades');
    }
};
