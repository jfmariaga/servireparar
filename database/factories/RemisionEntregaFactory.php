<?php

namespace Database\Factories;

use App\Models\RemisionEntrega;
use App\Models\SolicitudDespacho;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RemisionEntregaFactory extends Factory
{
    protected $model = RemisionEntrega::class;

    public function definition(): array
    {
        return [
            'numero' => 'REM-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'solicitud_id' => SolicitudDespacho::factory(),
            'generada_por' => User::factory(),
            'fecha' => now(),
        ];
    }
}
