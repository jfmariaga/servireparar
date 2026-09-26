<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remisiones_entrega', function (Blueprint $table) {
            $table->string('firma_fisica_foto')->nullable()->after('firma_entrega');
            $table->timestamp('firma_fisica_recibida_en')->nullable()->after('firma_fisica_foto');
        });
    }

    public function down(): void
    {
        Schema::table('remisiones_entrega', function (Blueprint $table) {
            $table->dropColumn(['firma_fisica_foto', 'firma_fisica_recibida_en']);
        });
    }
};
