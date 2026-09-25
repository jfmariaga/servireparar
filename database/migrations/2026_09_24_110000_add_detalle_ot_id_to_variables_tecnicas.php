<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variables_tecnicas', function (Blueprint $table) {
            $table->foreignId('detalle_ot_id')->nullable()->after('ot_id')->constrained('detalle_ot')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('variables_tecnicas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('detalle_ot_id');
        });
    }
};
