<?php

namespace Database\Factories;

use App\Models\CategoriaInventario;
use App\Models\Inventario;
use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventarioFactory extends Factory
{
    protected $model = Inventario::class;

    public function definition(): array
    {
        return [
            'codigo' => 'REP-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'nombre' => ucfirst($this->faker->words(2, true)),
            'tipo' => 'consumible',
            'categoria_id' => CategoriaInventario::factory(),
            'ubicacion' => null,
            'codigo_barras' => null,
            'unidad_medida_id' => UnidadMedida::factory(),
            'stock_actual' => 100,
            'stock_minimo' => 10,
            'costo_unitario' => $this->faker->randomFloat(2, 1000, 50000),
            'estado_herramienta' => null,
            'activo' => true,
        ];
    }

    public function herramienta(): static
    {
        return $this->state([
            'tipo' => 'herramienta',
            'stock_actual' => 1,
            'stock_minimo' => 0,
            'estado_herramienta' => 'disponible',
        ]);
    }
}
