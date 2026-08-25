<?php

namespace Database\Seeders;

use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

class UnidadesMedidaSeeder extends Seeder
{
    /**
     * Catálogo fijo inicial de unidades de medida (spec 003), para evitar variantes
     * del mismo valor por error de digitación (ej. "galon" vs "Galón" vs "GAL").
     */
    public function run(): void
    {
        $unidades = [
            'Unidad' => 'und',
            'Galón' => 'gal',
            'Litro' => 'lt',
            'Metro' => 'm',
            'Kilogramo' => 'kg',
            'Caja' => 'caja',
            'Par' => 'par',
            'Rollo' => 'rollo',
        ];

        foreach ($unidades as $nombre => $abreviatura) {
            UnidadMedida::firstOrCreate(['nombre' => $nombre], ['abreviatura' => $abreviatura]);
        }
    }
}
