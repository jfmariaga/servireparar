<?php

namespace Database\Factories;

use App\Models\ChecklistOt;
use App\Models\OrdenTrabajo;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChecklistOtFactory extends Factory
{
    protected $model = ChecklistOt::class;

    public function definition(): array
    {
        return [
            'ot_id' => OrdenTrabajo::factory(),
            'item' => $this->faker->sentence(4),
            'cumple' => null,
            'observaciones' => null,
        ];
    }

    public function cumplido(): static
    {
        return $this->state(fn () => ['cumple' => true]);
    }
}
