<?php

namespace Database\Factories;

use App\Models\DetalleSolicitudDespacho;
use App\Models\Inventario;
use App\Models\SolicitudDespacho;
use Illuminate\Database\Eloquent\Factories\Factory;

class DetalleSolicitudDespachoFactory extends Factory
{
    protected $model = DetalleSolicitudDespacho::class;

    public function definition(): array
    {
        return [
            'solicitud_id' => SolicitudDespacho::factory(),
            'origen' => 'inventario',
            'inventario_id' => Inventario::factory(),
            'descripcion' => $this->faker->words(2, true),
            'cantidad' => $this->faker->numberBetween(1, 10),
            'costo_unitario' => $this->faker->randomFloat(2, 1000, 50000),
            'motivo' => null,
        ];
    }

    public function compraExterna(): static
    {
        return $this->state([
            'origen' => 'compra_externa',
            'inventario_id' => null,
            'descripcion' => $this->faker->words(3, true),
            'proveedor_externo' => $this->faker->company(),
            'costo_compra_externa' => $this->faker->randomFloat(2, 5000, 200000),
            'motivo' => DetalleSolicitudDespacho::MOTIVO_COMPRA_EXTERNA,
        ]);
    }
}
