<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratistas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('especialidad', 100)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('correo', 150)->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();

            $table->index('correo');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratistas');
    }
};
