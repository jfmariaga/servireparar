<?php

namespace Database\Factories;

use App\Models\AuditoriaInventario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditoriaInventarioFactory extends Factory
{
    protected $model = AuditoriaInventario::class;

    public function definition(): array
    {
        return [
            'iniciada_por' => User::factory(),
            'fecha_inicio' => now(),
            'fecha_cierre' => null,
            'estado' => 'abierta',
        ];
    }
}
