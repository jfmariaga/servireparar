<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\SolicitudDespacho;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SolicitudDespachoFactory extends Factory
{
    protected $model = SolicitudDespacho::class;

    public function definition(): array
    {
        return [
            'numero' => 'SD-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'cliente_id' => Cliente::factory(),
            'vendedor_id' => User::factory(),
            'sede' => 'BAQ',
            'estado' => 'solicitada',
            'observaciones' => null,
            'fecha_solicitud' => now(),
        ];
    }
}
