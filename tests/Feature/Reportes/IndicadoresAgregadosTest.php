<?php

namespace Tests\Feature\Reportes;

use App\Models\DetalleOt;
use App\Models\EstadoOt;
use App\Models\OrdenTrabajo;
use App\Models\Tecnico;
use App\Services\Reportes\IndicadoresAgregadosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Spec 007, US2 (FR-002/FR-003/FR-004): cada indicador se verifica contra un
 * valor conocido (SC-003).
 */
class IndicadoresAgregadosTest extends TestCase
{
    use RefreshDatabase;

    private IndicadoresAgregadosService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IndicadoresAgregadosService();
        config(['ot.dias_umbral_vencimiento' => 2]);
    }

    public function test_ot_resumen_clasifica_abiertas_cerradas_vencidas_y_proximas(): void
    {
        // Abierta, sin problema de plazo (tiempo estimado 30 días, creada hoy).
        OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create(['tiempo_estimado_dias' => 30]);

        // Abierta y ya vencida (creada hace 10 días, estimado de 1 día).
        OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create([
            'tiempo_estimado_dias' => 1,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        // Abierta y próxima a vencer (estimado 2 días, creada hoy → vence dentro del umbral).
        OrdenTrabajo::factory()->enEstado(EstadoOt::PENDIENTE)->create(['tiempo_estimado_dias' => 2]);

        // Cerradas: finalizada y entregada.
        OrdenTrabajo::factory()->enEstado(EstadoOt::FINALIZADA)->create();
        OrdenTrabajo::factory()->enEstado(EstadoOt::ENTREGADA)->create();

        // Cancelada: no cuenta ni como abierta ni como cerrada.
        OrdenTrabajo::factory()->enEstado(EstadoOt::CANCELADA)->create();

        $resumen = $this->service->otResumen();

        $this->assertSame(3, $resumen['abiertas']);
        $this->assertSame(2, $resumen['cerradas']);
        $this->assertSame(1, $resumen['vencidas']);
        $this->assertSame(1, $resumen['proximas_a_vencer']);
    }

    public function test_cumplimiento_de_tiempos_compara_dias_trabajados_contra_estimado(): void
    {
        // Dentro del estimado: 2 días trabajados <= 3 estimados.
        $otA = OrdenTrabajo::factory()->enEstado(EstadoOt::ENTREGADA)->create(['tiempo_estimado_dias' => 3]);
        DetalleOt::factory()->for($otA, 'ordenTrabajo')->finalizada(2)->create();

        // Se pasó del estimado: 5 días trabajados > 2 estimados.
        $otB = OrdenTrabajo::factory()->enEstado(EstadoOt::FINALIZADA)->create(['tiempo_estimado_dias' => 2]);
        DetalleOt::factory()->for($otB, 'ordenTrabajo')->finalizada(5)->create();

        // OT abierta: no debe contar en el indicador.
        OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create(['tiempo_estimado_dias' => 5]);

        $this->assertEquals(50.0, $this->service->cumplimientoTiempos());
    }

    public function test_cumplimiento_de_tiempos_es_null_sin_ot_cerradas(): void
    {
        OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create(['tiempo_estimado_dias' => 5]);

        $this->assertNull($this->service->cumplimientoTiempos());
    }

    public function test_productividad_equipo_agrega_el_resumen_por_tecnico(): void
    {
        $tecnico = Tecnico::factory()->create();

        DetalleOt::factory()->create([
            'tecnico_id' => $tecnico->id,
            'estado_tarea' => 'finalizada',
            'dias_cumplimiento' => 2,
            'dias_trabajados' => 2,
            'fecha_fin' => now(),
        ]);

        $resultado = $this->service->productividadEquipo();

        $this->assertSame(1, $resultado['tecnicos_activos']);
        $this->assertSame(1, $resultado['tareas_finalizadas_total']);
        $this->assertEquals(100.0, $resultado['cumplimiento_promedio_pct']);
    }

    public function test_tareas_en_ejecucion_solo_incluye_las_iniciadas_sin_finalizar(): void
    {
        DetalleOt::factory()->enCurso()->create();
        DetalleOt::factory()->create(['estado_tarea' => 'pendiente']);
        DetalleOt::factory()->finalizada()->create();

        $this->assertCount(1, $this->service->tareasEnEjecucion());
    }
}
