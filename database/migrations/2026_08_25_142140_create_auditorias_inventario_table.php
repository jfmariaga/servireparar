<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iniciada_por')->constrained('users');
            $table->timestamp('fecha_inicio');
            $table->timestamp('fecha_cierre')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias_inventario');
    }
};
