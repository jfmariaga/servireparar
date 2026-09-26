<?php

namespace Database\Factories;

use App\Models\Cotizacion;
use Illuminate\Database\Eloquent\Factories\Factory;

class CotizacionFactory extends Factory
{
    protected $model = Cotizacion::class;

    public function definition(): array
    {
        return [
            'numero' => 'COT-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 4, '0', STR_PAD_LEFT),
            'cliente_id' => null,
            'equipo_id' => null,
            'estado' => 'en_revision',
            'correo_original_referencia' => null,
            'total' => 0,
        ];
    }
}
