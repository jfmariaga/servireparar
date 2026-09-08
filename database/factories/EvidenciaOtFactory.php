<?php

namespace Database\Factories;

use App\Models\EvidenciaOt;
use App\Models\OrdenTrabajo;
use Illuminate\Database\Eloquent\Factories\Factory;

class EvidenciaOtFactory extends Factory
{
    protected $model = EvidenciaOt::class;

    public function definition(): array
    {
        return [
            'ot_id' => OrdenTrabajo::factory(),
            'detalle_ot_id' => null,
            'tipo_registro' => 'proceso',
            'tipo_archivo' => 'image/jpeg',
            'url_archivo' => 'evidencias-ot/'.$this->faker->uuid().'.jpg',
            'descripcion' => $this->faker->sentence(3),
            'subida_por' => null,
            'fecha_subida' => now(),
        ];
    }
}
