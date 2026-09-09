<?php

namespace Database\Factories;

use App\Models\DetalleOt;
use App\Models\DetalleOtInsumo;
use App\Models\Inventario;
use Illuminate\Database\Eloquent\Factories\Factory;

class DetalleOtInsumoFactory extends Factory
{
    protected $model = DetalleOtInsumo::class;

    public function definition(): array
    {
        return [
            'detalle_ot_id' => DetalleOt::factory(),
            'inventario_id' => Inventario::factory()->state(['tipo' => 'consumible']),
            'cantidad' => $this->faker->numberBetween(1, 10),
        ];
    }
}
