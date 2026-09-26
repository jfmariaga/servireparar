<?php

namespace Database\Factories;

use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\Servicio;
use Illuminate\Database\Eloquent\Factories\Factory;

class DetalleCotizacionFactory extends Factory
{
    protected $model = DetalleCotizacion::class;

    public function definition(): array
    {
        $cantidad = $this->faker->randomFloat(2, 1, 5);
        $costoUnitario = $this->faker->randomFloat(2, 10000, 100000);

        return [
            'cotizacion_id' => Cotizacion::factory(),
            'tipo_item' => 'servicio',
            'servicio_id' => Servicio::factory(),
            'inventario_id' => null,
            'cantidad' => $cantidad,
            'costo_unitario' => $costoUnitario,
            'valor_total' => round($cantidad * $costoUnitario, 2),
        ];
    }
}
