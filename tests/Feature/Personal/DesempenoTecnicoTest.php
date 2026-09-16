<?php

namespace Tests\Feature\Personal;

use App\Models\DetalleOt;
use App\Models\OrdenTrabajo;
use App\Models\Tecnico;
use App\Services\Personal\DesempenoTecnicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Spec 004, US3 (FR-003, SC-003): tiempos de ejecución, participación en OT y
 * cumplimiento de plazo por técnico, con datos conocidos.
 */
class DesempenoTecnicoTest extends TestCase
{
    use RefreshDatabase;

    private DesempenoTecnicoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DesempenoTecnicoService();
    }

    public function test_calcula_tiempo_promedio_ot_y_cumplimiento_con_datos_conocidos(): void
    {
        $tecnico = Tecnico::factory()->create();
        $otA = OrdenTrabajo::factory()->create();
        $otB = OrdenTrabajo::factory()->create();

        // Cumple el plazo: 2 días trabajados <= 3 de plazo.
        DetalleOt::factory()->for($otA, 'ordenTrabajo')->create([
            'tecnico_id' => $tecnico->id,
            'estado_tarea' => 'finalizada',
            'dias_cumplimiento' => 3,
            'dias_trabajados' => 2,
            'fecha_inicio' => Carbon::parse('2026-09-01'),
            'fecha_fin' => Carbon::parse('2026-09-03'),
        ]);

        // Se pasa del plazo: 4 días trabajados > 2 de plazo.
        DetalleOt::factory()->for($otB, 'ordenTrabajo')->create([
            'tecnico_id' => $tecnico->id,
            'estado_tarea' => 'finalizada',
            'dias_cumplimiento' => 2,
            'dias_trabajados' => 4,
            'fecha_inicio' => Carbon::parse('2026-09-05'),
            'fecha_fin' => Carbon::parse('2026-09-09'),
        ]);

        // Tarea aún pendiente: no debe contar en el resumen.
        DetalleOt::factory()->for($otB, 'ordenTrabajo')->create([
            'tecnico_id' => $tecnico->id,
            'estado_tarea' => 'pendiente',
        ]);

        $resumen = $this->service->resumen($tecnico);

        $this->assertSame(2, $resumen['ot_count']);
        $this->assertSame(2, $resumen['tareas_finalizadas']);
        $this->assertEquals(3.0, $resumen['tiempo_promedio_dias']); // (2 + 4) / 2
        $this->assertEquals(50.0, $resumen['cumplimiento_pct']); // 1 de 2 dentro de plazo
    }

    public function test_filtra_por_rango_de_fechas_segun_fecha_fin(): void
    {
        $tecnico = Tecnico::factory()->create();

        DetalleOt::factory()->create([
            'tecnico_id' => $tecnico->id,
            'estado_tarea' => 'finalizada',
            'dias_trabajados' => 1,
            'fecha_fin' => Carbon::parse('2026-08-01'),
        ]);
        DetalleOt::factory()->create([
            'tecnico_id' => $tecnico->id,
            'estado_tarea' => 'finalizada',
            'dias_trabajados' => 5,
            'fecha_fin' => Carbon::parse('2026-09-10'),
        ]);

        $resumen = $this->service->resumen($tecnico, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertSame(1, $resumen['tareas_finalizadas']);
        $this->assertEquals(5.0, $resumen['tiempo_promedio_dias']);
    }

    public function test_tecnico_sin_tareas_finalizadas_tiene_metricas_nulas(): void
    {
        $tecnico = Tecnico::factory()->create();

        $resumen = $this->service->resumen($tecnico);

        $this->assertSame(0, $resumen['ot_count']);
        $this->assertSame(0, $resumen['tareas_finalizadas']);
        $this->assertNull($resumen['tiempo_promedio_dias']);
        $this->assertNull($resumen['cumplimiento_pct']);
    }

    public function test_resumen_por_tecnico_excluye_a_quienes_no_tienen_tareas_finalizadas(): void
    {
        $conHistorial = Tecnico::factory()->create();
        Tecnico::factory()->create(); // sin tareas

        DetalleOt::factory()->create([
            'tecnico_id' => $conHistorial->id,
            'estado_tarea' => 'finalizada',
            'dias_trabajados' => 2,
            'fecha_fin' => Carbon::now(),
        ]);

        $resultado = $this->service->resumenPorTecnico();

        $this->assertCount(1, $resultado);
        $this->assertSame($conHistorial->id, $resultado->first()['tecnico_id']);
    }
}
