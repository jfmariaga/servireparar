<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 12 / D12: el estado inicial de la OT se muestra como "Planificación"
 * (antes "En revisión"). Solo cambia el `nombre`; el slug `en_revision` y toda
 * la lógica siguen igual. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('estados_ot')->where('slug', 'en_revision')->update(['nombre' => 'Planificación']);
    }

    public function down(): void
    {
        DB::table('estados_ot')->where('slug', 'en_revision')->update(['nombre' => 'En revisión']);
    }
};
