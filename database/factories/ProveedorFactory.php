<?php

namespace Database\Factories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->company(),
            'nit' => $this->faker->unique()->numerify('8########-#'),
            'correo' => $this->faker->unique()->companyEmail(),
            'direccion' => $this->faker->address(),
            'estado' => 'activo',
        ];
    }
}
