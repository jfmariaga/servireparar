<?php

namespace Database\Factories;

use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompraFactory extends Factory
{
    protected $model = Compra::class;

    public function definition(): array
    {
        return [
            'numero' => 'COM-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 4, '0', STR_PAD_LEFT),
            'proveedor_id' => Proveedor::factory(),
            'estado' => 'recepcion',
            'observaciones' => null,
            'total' => 0,
            'creado_por' => User::factory(),
        ];
    }
}
