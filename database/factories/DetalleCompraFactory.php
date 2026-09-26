<?php

namespace Database\Factories;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Inventario;
use Illuminate\Database\Eloquent\Factories\Factory;

class DetalleCompraFactory extends Factory
{
    protected $model = DetalleCompra::class;

    public function definition(): array
    {
        $cantidad = $this->faker->randomFloat(2, 1, 20);
        $costoUnitario = $this->faker->randomFloat(2, 1000, 50000);

        return [
            'compra_id' => Compra::factory(),
            'inventario_id' => Inventario::factory(),
            'cantidad' => $cantidad,
            'costo_unitario' => $costoUnitario,
            'valor_total' => round($cantidad * $costoUnitario, 2),
        ];
    }
}
