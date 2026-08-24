<?php

namespace Database\Factories;

use App\Models\Contratista;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContratistaFactory extends Factory
{
    protected $model = Contratista::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->company(),
            'especialidad' => $this->faker->randomElement(['Soldadura', 'Calcomanías', 'Pintura', 'Eléctrico']),
            'telefono' => $this->faker->numerify('3#########'),
            'correo' => $this->faker->unique()->companyEmail(),
            'estado' => 'activo',
        ];
    }
}
