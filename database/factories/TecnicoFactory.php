<?php

namespace Database\Factories;

use App\Models\Especialidad;
use App\Models\SueldoTecnico;
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
            'fecha_ingreso' => $this->faker->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d'),
            'cargo' => $this->faker->randomElement(['Técnico de taller', 'Técnico senior', 'Ayudante']),
            'tipo_contrato' => $this->faker->randomElement(['termino_fijo', 'indefinido', 'prestacion_servicios']),
            'activo' => true,
        ];
    }

    /**
     * Crea el técnico con un sueldo vigente en el histórico.
     */
    public function conSueldo(float $sueldo = 2400000, ?string $vigenteDesde = null): static
    {
        return $this->afterCreating(function (Tecnico $tecnico) use ($sueldo, $vigenteDesde) {
            SueldoTecnico::factory()->for($tecnico)->create([
                'sueldo' => $sueldo,
                'vigente_desde' => $vigenteDesde ?? now()->subMonths(6)->toDateString(),
            ]);
        });
    }
}
