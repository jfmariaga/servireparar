<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_inventario', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('prefijo_codigo', 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_inventario');
    }
};
