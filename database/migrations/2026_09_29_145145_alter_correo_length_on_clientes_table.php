<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía `correo` para admitir varios contactos por cliente separados por
 * coma (p. ej. "a@x.com,b@y.com"), ya que a veces hay más de un contacto.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE clientes MODIFY correo VARCHAR(500) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE clientes MODIFY correo VARCHAR(150) NULL');
    }
};
