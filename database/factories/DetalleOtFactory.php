<?php

namespace Database\Factories;

use App\Models\DetalleOt;
use App\Models\OrdenTrabajo;
use App\Models\Tecnico;
use Illuminate\Database\Eloquent\Factories\Factory;

class DetalleOtFactory extends Factory
{
    protected $model = DetalleOt::class;

    public function definition(): array
    {
        return [
            'ot_id' => OrdenTrabajo::factory(),
            'descripcion' => $this->faker->sentence(),
            'tecnico_id' => Tecnico::factory(),
            'estado_tarea' => 'pendiente',
            'dias_trabajados' => null,
        ];
    }

    public function finalizada(float $dias = 1): static
    {
        return $this->state(fn () => [
            'estado_tarea' => 'finalizada',
            'fecha_inicio' => now()->subDays((int) ceil($dias)),
            'fecha_fin' => now(),
            'dias_trabajados' => $dias,
        ]);
    }

    public function enCurso(): static
    {
        return $this->state(fn () => [
            'estado_tarea' => 'en_curso',
            'fecha_inicio' => now(),
        ]);
    }
}
