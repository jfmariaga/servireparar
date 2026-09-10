<?php

namespace Database\Factories;

use App\Models\Inventario;
use App\Models\PrestamoHerramienta;
use App\Models\Tecnico;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrestamoHerramientaFactory extends Factory
{
    protected $model = PrestamoHerramienta::class;

    public function definition(): array
    {
        return [
            'ot_id' => null,
            'tecnico_id' => Tecnico::factory(),
            'detalle_ot_id' => null,
            'inventario_id' => Inventario::factory()->herramienta(),
            'estado' => 'solicitada',
            'solicitada_en' => now(),
        ];
    }

    public function entregada(): static
    {
        return $this->state(fn () => ['estado' => 'entregada']);
    }

    public function devuelta(string $estado = 'disponible'): static
    {
        return $this->state(fn () => [
            'estado' => 'devuelta',
            'devuelta_en' => now(),
            'estado_devolucion' => $estado,
        ]);
    }
}
