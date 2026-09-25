<?php

namespace Database\Factories;

use App\Models\OrdenTrabajo;
use App\Models\VariableTecnica;
use Illuminate\Database\Eloquent\Factories\Factory;

class VariableTecnicaFactory extends Factory
{
    protected $model = VariableTecnica::class;

    public function definition(): array
    {
        return [
            'ot_id' => OrdenTrabajo::factory(),
            'nombre' => $this->faker->randomElement(['Temperatura', 'Presión', 'Voltaje']),
            'valor' => (string) $this->faker->randomFloat(1, 1, 100),
            'unidad' => $this->faker->randomElement(['°C', 'psi', 'V']),
            'registrado_por' => null,
        ];
    }
}
