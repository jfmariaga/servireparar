<?php

namespace Database\Factories;

use App\Models\Cotizacion;
use App\Models\MensajeCotizacion;
use Illuminate\Database\Eloquent\Factories\Factory;

class MensajeCotizacionFactory extends Factory
{
    protected $model = MensajeCotizacion::class;

    public function definition(): array
    {
        return [
            'cotizacion_id' => Cotizacion::factory(),
            'autor_tipo' => 'sistema',
            'autor_id' => null,
            'contenido' => $this->faker->sentence(),
            'adjunto_url' => null,
            'message_id_correo' => null,
        ];
    }
}
