<?php

namespace Database\Factories;

use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use App\Models\OtHerramienta;
use Illuminate\Database\Eloquent\Factories\Factory;

class OtHerramientaFactory extends Factory
{
    protected $model = OtHerramienta::class;

    public function definition(): array
    {
        return [
            'ot_id' => OrdenTrabajo::factory(),
            'inventario_id' => Inventario::factory()->herramienta(),
            'asignada_por' => null,
            'asignada_en' => now(),
        ];
    }

    public function devuelta(string $estado = 'disponible'): static
    {
        return $this->state(fn () => [
            'devuelta_en' => now(),
            'estado_devolucion' => $estado,
        ]);
    }
}
