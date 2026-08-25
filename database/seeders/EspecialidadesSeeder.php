<?php

namespace Database\Seeders;

use App\Models\Especialidad;
use Illuminate\Database\Seeder;

class EspecialidadesSeeder extends Seeder
{
    /**
     * Catálogo fijo inicial de especialidades técnicas (spec 004, FR-001).
     * Ajustable por el Administrador según la operación real de SERVIREPARAR.
     */
    public function run(): void
    {
        $especialidades = [
            'Eléctrico',
            'Mecánico',
            'Refrigeración y aire acondicionado',
            'Electrónico',
            'Estructuras y soldadura',
            'General',
        ];

        foreach ($especialidades as $nombre) {
            Especialidad::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
