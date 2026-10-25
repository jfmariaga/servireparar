<?php

namespace Database\Factories;

use App\Models\Cotizacion;
use Illuminate\Database\Eloquent\Factories\Factory;

class CotizacionFactory extends Factory
{
    protected $model = Cotizacion::class;

    public function definition(): array
    {
        return [
            'numero' => 'BAQ-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'sede' => 'BAQ',
            'cliente_id' => null,
            'equipo_id' => null,
            'estado' => 'en_revision',
            'correo_original_referencia' => null,
            'descuento_porcentaje' => 0,
            'subtotal' => 0,
            'descuento_valor' => 0,
            'iva_porcentaje' => 19,
            'iva_valor' => 0,
            'total' => 0,
        ];
    }
}
