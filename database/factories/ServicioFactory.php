<?php

namespace Database\Factories;

use App\Models\Servicio;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServicioFactory extends Factory
{
    protected $model = Servicio::class;

    public function definition(): array
    {
        return [
            'nombre' => ucfirst($this->faker->words(3, true)),
            'costo_unitario' => $this->faker->randomFloat(2, 20000, 300000),
            'unidad_medida' => 'servicio',
            'activo' => true,
        ];
    }
}
