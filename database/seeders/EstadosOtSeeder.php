<?php

namespace Database\Seeders;

use App\Models\EstadoOt;
use Illuminate\Database\Seeder;

class EstadosOtSeeder extends Seeder
{
    /**
     * Estados del ciclo de vida de una OT (spec 002, FR-004). "Entregada" es
     * el único estado terminal.
     */
    public function run(): void
    {
        $estados = [
            ['slug' => EstadoOt::EN_REVISION, 'nombre' => 'En revisión', 'orden' => 1, 'es_terminal' => false],
            ['slug' => EstadoOt::PENDIENTE, 'nombre' => 'Pendiente', 'orden' => 2, 'es_terminal' => false],
            ['slug' => EstadoOt::EN_CURSO, 'nombre' => 'En curso', 'orden' => 3, 'es_terminal' => false],
            ['slug' => EstadoOt::FINALIZADA, 'nombre' => 'Finalizada', 'orden' => 4, 'es_terminal' => false],
            ['slug' => EstadoOt::ENTREGADA, 'nombre' => 'Entregada', 'orden' => 5, 'es_terminal' => true],
        ];

        foreach ($estados as $estado) {
            EstadoOt::updateOrCreate(['slug' => $estado['slug']], $estado);
        }
    }
}
