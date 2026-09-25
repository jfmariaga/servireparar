<?php

namespace Tests\Feature\OrdenesTrabajo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Backfill de `dias_trabajados` (fórmula vieja por días calendario → horas
 * reales / jornada) para tareas ya finalizadas antes del cambio a costeo por
 * horas (spec 004).
 */
class RecalcularDiasTrabajadosPorHorasTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    public function test_recalcula_las_tareas_finalizadas_con_la_formula_vieja(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tarea = $ot->tareas()->first();
        $tarea->update([
            'estado_tarea' => 'finalizada',
            'fecha_inicio' => Carbon::parse('2026-09-01 08:00:00'),
            'fecha_fin' => Carbon::parse('2026-09-01 10:00:00'),
            'dias_trabajados' => 1, // calculado con la fórmula vieja (mismo día → 1 día)
        ]);

        $this->artisan('ot:recalcular-dias-trabajados', ['--force' => true])
            ->assertExitCode(0);

        $this->assertEquals(0.25, (float) $tarea->fresh()->dias_trabajados); // 2 horas / jornada de 8h
    }

    public function test_dry_run_no_escribe_nada(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tarea = $ot->tareas()->first();
        $tarea->update([
            'estado_tarea' => 'finalizada',
            'fecha_inicio' => Carbon::parse('2026-09-01 08:00:00'),
            'fecha_fin' => Carbon::parse('2026-09-01 10:00:00'),
            'dias_trabajados' => 1,
        ]);

        $this->artisan('ot:recalcular-dias-trabajados', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertEquals(1, (float) $tarea->fresh()->dias_trabajados);
    }

    public function test_ignora_tareas_no_finalizadas_y_las_que_ya_coinciden(): void
    {
        $ot = $this->crearOt(tareas: 2);
        $tareas = $ot->tareas()->orderBy('id')->get();

        // Pendiente: nunca se toca.
        $tareas[0]->update(['estado_tarea' => 'pendiente', 'dias_trabajados' => null]);

        // Finalizada, pero el valor guardado ya coincide con la fórmula nueva.
        $tareas[1]->update([
            'estado_tarea' => 'finalizada',
            'fecha_inicio' => Carbon::parse('2026-09-01 08:00:00'),
            'fecha_fin' => Carbon::parse('2026-09-01 16:00:00'),
            'dias_trabajados' => 1, // 8 horas / 8 = 1, igual en ambas fórmulas
        ]);

        $this->artisan('ot:recalcular-dias-trabajados', ['--force' => true])
            ->assertExitCode(0);

        $this->assertNull($tareas[0]->fresh()->dias_trabajados);
        $this->assertEquals(1, (float) $tareas[1]->fresh()->dias_trabajados);
    }
}
