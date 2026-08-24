<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->company(),
            'nit' => $this->faker->unique()->numerify('9########-#'),
            'telefono' => $this->faker->numerify('3#########'),
            'correo' => $this->faker->unique()->companyEmail(),
            'direccion' => $this->faker->address(),
            'estado' => 'activo',
        ];
    }
}
