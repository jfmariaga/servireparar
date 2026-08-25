<?php

namespace Database\Factories;

use App\Models\Equipo;
use App\Models\MantenimientoPreventivo;
use Illuminate\Database\Eloquent\Factories\Factory;

class MantenimientoPreventivoFactory extends Factory
{
    protected $model = MantenimientoPreventivo::class;

    public function definition(): array
    {
        return [
            'equipo_id' => Equipo::factory(['periodicidad_mantenimiento_dias' => 90]),
            'ultima_fecha' => now()->subDays(80),
            'proxima_fecha' => now()->addDays(10),
            'alerta_disparada' => false,
        ];
    }
}
