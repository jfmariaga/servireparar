<?php

namespace Database\Factories;

use App\Models\DetalleOt;
use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use App\Models\SolicitudInsumoOt;
use Illuminate\Database\Eloquent\Factories\Factory;

class SolicitudInsumoOtFactory extends Factory
{
    protected $model = SolicitudInsumoOt::class;

    public function definition(): array
    {
        return [
            'ot_id' => OrdenTrabajo::factory(),
            'detalle_ot_id' => DetalleOt::factory(),
            'inventario_id' => Inventario::factory(),
            'cantidad' => $this->faker->numberBetween(1, 10),
            'estado' => 'pendiente',
            'solicitada_por' => null,
        ];
    }
}
