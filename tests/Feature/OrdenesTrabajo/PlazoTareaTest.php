<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Models\Cliente;
use App\Models\Prioridad;
use App\Models\Tecnico;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 13 · D17/D18 — plazo de cumplimiento por tarea: la suma de los plazos de
 * las tareas activas no puede superar el tiempo estimado de la OT; una tarea solo
 * se edita mientras esté "pendiente"; una tarea con el plazo vencido va "atrasada".
 */
class PlazoTareaTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    private function crear(array $tareas, ?float $estimado = 10): \App\Models\OrdenTrabajo
    {
        return app(OrdenTrabajoService::class)->crear($this->jefeDeTaller(), [
            'cliente_id' => Cliente::factory()->create()->id,
            'prioridad_id' => Prioridad::where('nombre', 'Media')->value('id'),
            'descripcion' => 'OT plazos',
            'tiempo_estimado_dias' => $estimado,
        ], collect($tareas)->map(fn ($t) => array_merge([
            'descripcion' => $t['d'] ?? 'Tarea',
            'tecnico_id' => Tecnico::factory()->conSueldo()->create()->id,
        ], $t))->all());
    }

    public function test_crear_con_plazos_dentro_del_estimado_funciona(): void
    {
        $ot = $this->crear([
            ['dias_cumplimiento' => 4],
            ['dias_cumplimiento' => 5],
        ], estimado: 10);

        $this->assertEqualsWithDelta(9.0, $ot->diasCumplimientoAsignados(), 0.01);
    }

    public function test_crear_con_plazos_que_superan_el_estimado_se_bloquea(): void
    {
        $this->expectException(ValidationException::class);

        $this->crear([
            ['dias_cumplimiento' => 6],
            ['dias_cumplimiento' => 6],
        ], estimado: 10);
    }

    public function test_agregar_tarea_que_excede_el_estimado_se_bloquea(): void
    {
        $ot = $this->crear([['dias_cumplimiento' => 8]], estimado: 10);

        try {
            app(OrdenTrabajoService::class)->agregarTarea($ot->fresh(), $this->jefeDeTaller(), [
                'descripcion' => 'Otra', 'tecnico_id' => Tecnico::factory()->conSueldo()->create()->id,
                'dias_cumplimiento' => 5,
            ]);
            $this->fail('Debía bloquear: 8 + 5 > 10.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('supera el tiempo estimado', $e->getMessage());
        }
    }

    public function test_solo_se_edita_una_tarea_pendiente(): void
    {
        $ot = $this->crear([['dias_cumplimiento' => 3], ['dias_cumplimiento' => 3]]);
        $tarea = $ot->tareas()->first();
        $tarea->update(['estado_tarea' => 'en_curso', 'fecha_inicio' => now()]);

        $this->expectException(ValidationException::class);
        app(OrdenTrabajoService::class)->actualizarTarea($tarea->fresh(), $this->jefeDeTaller(), ['dias_cumplimiento' => 2]);
    }

    public function test_editar_el_plazo_de_una_tarea_revalida_la_suma(): void
    {
        $ot = $this->crear([['dias_cumplimiento' => 3], ['dias_cumplimiento' => 3]], estimado: 10);
        $tarea = $ot->tareas()->first();

        // 8 (nuevo) + 3 (la otra) = 11 > 10 → bloqueo.
        try {
            app(OrdenTrabajoService::class)->actualizarTarea($tarea->fresh(), $this->jefeDeTaller(), ['dias_cumplimiento' => 8]);
            $this->fail('Debía bloquear la suma.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('supera el tiempo estimado', $e->getMessage());
        }

        // 6 + 3 = 9 ≤ 10 → permitido.
        app(OrdenTrabajoService::class)->actualizarTarea($tarea->fresh(), $this->jefeDeTaller(), ['dias_cumplimiento' => 6]);
        $this->assertEqualsWithDelta(6.0, (float) $tarea->fresh()->dias_cumplimiento, 0.01);
    }

    public function test_corregir_no_puede_bajar_el_estimado_por_debajo_de_los_plazos(): void
    {
        $ot = $this->crear([['dias_cumplimiento' => 5], ['dias_cumplimiento' => 4]], estimado: 10);

        $this->expectException(ValidationException::class);
        app(OrdenTrabajoService::class)->corregir($ot->fresh(), $this->jefeDeTaller(), ['tiempo_estimado_dias' => 7]);
    }

    public function test_una_tarea_con_plazo_vencido_esta_atrasada(): void
    {
        $ot = $this->crear([['dias_cumplimiento' => 2], ['dias_cumplimiento' => 2]]);
        $tarea = $ot->tareas()->first();
        $tarea->update(['estado_tarea' => 'en_curso', 'fecha_inicio' => now()->subDays(5)]);

        $this->assertTrue($tarea->fresh()->estaAtrasada());
        $this->assertTrue($ot->fresh()->tieneTareasAtrasadas());

        $tarea->update(['estado_tarea' => 'finalizada', 'fecha_fin' => now()]);
        $this->assertFalse($tarea->fresh()->estaAtrasada(), 'Una tarea finalizada no está atrasada.');
    }

    public function test_sin_liberar_una_tarea_no_puede_estar_atrasada(): void
    {
        $ot = $this->crear([['dias_cumplimiento' => 1], ['dias_cumplimiento' => 1]]);

        // OT en "Planificación": no hay fecha de referencia → no hay atraso.
        $this->assertFalse($ot->tareas()->first()->estaAtrasada());
    }
}
