<?php

namespace Database\Factories;

use App\Models\SueldoTecnico;
use App\Models\Tecnico;
use Illuminate\Database\Eloquent\Factories\Factory;

class SueldoTecnicoFactory extends Factory
{
    protected $model = SueldoTecnico::class;

    public function definition(): array
    {
        return [
            'tecnico_id' => Tecnico::factory(),
            'sueldo' => $this->faker->numberBetween(1_800_000, 5_000_000),
            'vigente_desde' => now()->subMonths(6)->toDateString(),
            'registrado_por' => null,
        ];
    }
}
