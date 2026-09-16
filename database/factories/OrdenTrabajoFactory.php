<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\EstadoOt;
use App\Models\OrdenTrabajo;
use App\Models\Prioridad;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrdenTrabajoFactory extends Factory
{
    protected $model = OrdenTrabajo::class;

    public function definition(): array
    {
        return [
            'numero_ot' => 'OTSV-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'cliente_id' => Cliente::factory(),
            'equipo_id' => null,
            'prioridad_id' => fn () => Prioridad::firstOrCreate(['nombre' => 'Media'], ['nivel' => 2])->id,
            'estado_id' => fn () => EstadoOt::firstOrCreate(
                ['slug' => EstadoOt::EN_REVISION],
                ['nombre' => 'En revisión', 'orden' => 1, 'es_terminal' => false],
            )->id,
            'tipo_servicio' => 'taller',
            'descripcion' => $this->faker->sentence(),
            'tiempo_estimado_dias' => $this->faker->randomFloat(1, 1, 10),
            'valor_proyecto' => null,
            'creado_por' => User::factory(),
        ];
    }

    public function enEstado(string $slug): static
    {
        $nombres = [
            EstadoOt::EN_REVISION => ['En revisión', 1, false],
            EstadoOt::PENDIENTE => ['Pendiente', 2, false],
            EstadoOt::EN_CURSO => ['En curso', 3, false],
            EstadoOt::FINALIZADA => ['Finalizada', 4, false],
            EstadoOt::ENTREGADA => ['Entregada', 5, true],
            EstadoOt::CANCELADA => ['Cancelada', 9, true],
        ];

        return $this->state(fn () => [
            'estado_id' => EstadoOt::firstOrCreate(
                ['slug' => $slug],
                ['nombre' => $nombres[$slug][0], 'orden' => $nombres[$slug][1], 'es_terminal' => $nombres[$slug][2]],
            )->id,
        ]);
    }

    public function conValorProyecto(float $valor): static
    {
        return $this->state(fn () => ['valor_proyecto' => $valor]);
    }
}
