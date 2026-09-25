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

    public function test_ot_en_categoria_devuelve_las_mismas_ot_que_cuenta_ot_resumen(): void
    {
        $vencida = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create([
            'tiempo_estimado_dias' => 1,
            'created_at' => Carbon::now()->subDays(10),
        ]);
        $proxima = OrdenTrabajo::factory()->enEstado(EstadoOt::PENDIENTE)->create(['tiempo_estimado_dias' => 2]);
        $cerrada = OrdenTrabajo::factory()->enEstado(EstadoOt::FINALIZADA)->create();

        $this->assertSame([$vencida->id], $this->service->otEnCategoria('vencidas')->pluck('id')->all());
        $this->assertSame([$proxima->id], $this->service->otEnCategoria('proximas_a_vencer')->pluck('id')->all());
        $this->assertSame([$cerrada->id], $this->service->otEnCategoria('cerradas')->pluck('id')->all());
        $this->assertCount(2, $this->service->otEnCategoria('abiertas'));
    }

    public function test_ots_del_piso_incluye_pendiente_y_en_curso_y_excluye_planificacion_y_terminales(): void
    {
        $porIniciar = OrdenTrabajo::factory()->enEstado(EstadoOt::PENDIENTE)->create();
        $enCurso = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create();

        // En planificación (EN_REVISION por defecto): no debe verse.
        OrdenTrabajo::factory()->create();

        // Ya cerrada (terminal): tampoco debe verse.
        OrdenTrabajo::factory()->enEstado(EstadoOt::ENTREGADA)->create();

        $piso = $this->service->otsDelPiso();

        $this->assertSame([$porIniciar->id, $enCurso->id], $piso->pluck('id')->all());
    }

    public function test_ots_del_piso_trae_el_conteo_de_avance_de_tareas(): void
    {
        $ot = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create();
        DetalleOt::factory()->for($ot, 'ordenTrabajo')->finalizada()->create();
        DetalleOt::factory()->for($ot, 'ordenTrabajo')->enCurso()->create();

        $tarjeta = $this->service->otsDelPiso()->firstWhere('id', $ot->id);

        $this->assertSame(2, $tarjeta->tareas_total);
        $this->assertSame(1, $tarjeta->tareas_finalizadas_count);
        $this->assertCount(1, $tarjeta->tareas); // solo la activa (en_curso), la finalizada no se carga
    }

    public function test_tendencia_ot_en_curso_cuenta_solapes_por_hora_y_tipo_servicio(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-23 10:00:00'));

        $otTaller = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create(['tipo_servicio' => 'taller']);
        $otDomicilio = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create(['tipo_servicio' => 'domicilio']);

        // Taller: activa de 08:00 a 09:00 (cubre el bucket de las 08:00, no el de las 09:00).
        DetalleOt::factory()->for($otTaller, 'ordenTrabajo')->create([
            'estado_tarea' => 'finalizada',
            'fecha_inicio' => Carbon::parse('2026-09-23 08:00:00'),
            'fecha_fin' => Carbon::parse('2026-09-23 09:00:00'),
        ]);

        // Domicilio: iniciada a las 09:30, sigue en curso (sin fecha_fin) — cubre 09:00 y 10:00.
        DetalleOt::factory()->for($otDomicilio, 'ordenTrabajo')->create([
            'estado_tarea' => 'en_curso',
            'fecha_inicio' => Carbon::parse('2026-09-23 09:30:00'),
            'fecha_fin' => null,
        ]);

        $buckets = collect($this->service->tendenciaOtEnCurso(3))->keyBy('hora');

        $this->assertSame(1, $buckets['08:00']['taller']);
        $this->assertSame(0, $buckets['08:00']['domicilio']);
        $this->assertSame(0, $buckets['09:00']['taller']);
        $this->assertSame(1, $buckets['09:00']['domicilio']);
        $this->assertSame(0, $buckets['10:00']['taller']);
        $this->assertSame(1, $buckets['10:00']['domicilio']);

        Carbon::setTestNow();
    }
}
