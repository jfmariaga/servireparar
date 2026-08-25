<?php

namespace Database\Factories;

use App\Models\CategoriaInventario;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoriaInventarioFactory extends Factory
{
    protected $model = CategoriaInventario::class;

    public function definition(): array
    {
        return [
            'nombre' => ucfirst($this->faker->unique()->word()),
            'prefijo_codigo' => 'REP-',
        ];
    }
}
