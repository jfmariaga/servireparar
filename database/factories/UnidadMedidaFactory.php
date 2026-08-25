<?php

namespace Database\Factories;

use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnidadMedidaFactory extends Factory
{
    protected $model = UnidadMedida::class;

    public function definition(): array
    {
        return [
            'nombre' => ucfirst($this->faker->unique()->word()),
            'abreviatura' => strtolower($this->faker->lexify('???')),
            'activo' => true,
        ];
    }
}
