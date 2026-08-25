<?php

namespace Database\Factories;

use App\Models\Especialidad;
use App\Models\Tecnico;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TecnicoFactory extends Factory
{
    protected $model = Tecnico::class;

    public function definition(): array
    {
        return [
            'usuario_id' => User::factory(),
            'especialidad_id' => Especialidad::factory(),
            'tarifa_hora' => $this->faker->randomFloat(2, 15000, 60000),
            'activo' => true,
        ];
    }
}
