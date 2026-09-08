<?php

namespace Database\Factories;

use App\Models\Contratista;
use App\Models\OrdenTrabajo;
use App\Models\OtManoObraContratista;
use Illuminate\Database\Eloquent\Factories\Factory;

class OtManoObraContratistaFactory extends Factory
{
    protected $model = OtManoObraContratista::class;

    public function definition(): array
    {
        return [
            'ot_id' => OrdenTrabajo::factory(),
            'contratista_id' => Contratista::factory(),
            'especialidad' => $this->faker->randomElement(['Soldadura', 'Calcomanías', 'Pintura']),
            'cantidad' => 1,
            'valor' => $this->faker->numberBetween(100_000, 800_000),
        ];
    }
}
