<?php

namespace Database\Seeders;

use App\Models\Prioridad;
use Illuminate\Database\Seeder;

class PrioridadesSeeder extends Seeder
{
    /**
     * Niveles de prioridad de una OT (spec 002). Fijos, sin editor en UI.
     */
    public function run(): void
    {
        $niveles = [
            'Baja' => 1,
            'Media' => 2,
            'Alta' => 3,
            'Urgente' => 4,
        ];

        foreach ($niveles as $nombre => $nivel) {
            Prioridad::firstOrCreate(['nombre' => $nombre], ['nivel' => $nivel]);
        }
    }
}
