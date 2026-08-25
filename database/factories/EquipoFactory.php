<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Equipo;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipoFactory extends Factory
{
    protected $model = Equipo::class;

    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'tipo' => $this->faker->randomElement(['Aire acondicionado', 'Compresor', 'Motor eléctrico', 'Escalera']),
            'marca' => $this->faker->company(),
            'modelo' => strtoupper($this->faker->bothify('??-####')),
            'serie' => $this->faker->unique()->bothify('SN-########'),
            'ubicacion' => $this->faker->streetAddress(),
            'estado' => 'operativo',
            'periodicidad_mantenimiento_dias' => null,
        ];
    }
}
