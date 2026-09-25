<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Enums\RolPrioridad;
use App\Services\OrdenTrabajo\CosteoOtService;
use App\Services\OrdenTrabajo\EstadoOtService;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class FlujoEstadoTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    public function test_iniciar_la_primera_tarea_pasa_la_ot_a_en_curso(): void
    {
        $ot = $this->crearOt();
        $this->assertSame('en_revision', $ot->estado->slug);
        $tarea = $ot->tareas()->first();
        $tecnicoUser = $tarea->tecnico->usuario;
        $tecnicoUser->assignRole(RolPrioridad::Tecnico->value);

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('planificar')
            ->assertHasNoErrors();

        Volt::actingAs($tecnicoUser)
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot->fresh()])
            ->call('iniciarTarea', $tarea->id)
            ->assertHasNoErrors();

        $this->assertSame('en_curso', $ot->fresh()->estado->slug);
        $this->assertDatabaseHas('ot_eventos', ['ot_id' => $ot->id, 'tipo' => 'cambio_estado']);
    }

    public function test_no_pasa_a_finalizada_si_el_checklist_esta_incompleto(): void
    {
        $ot = $this->crearOt(tareas: 2);
        $this->finalizarTodasLasTareas($ot);
        // checklist con un ítem sin responder
        $ot->checklist()->create(['item' => 'Prueba de funcionamiento']);

        app(EstadoOtService::class)->recalcular($ot->fresh());

        $this->assertSame('en_curso', $ot->fresh()->estado->slug);
        $this->assertFalse(app(EstadoOtService::class)->puedeFinalizar($ot->fresh()));
    }

    public function test_pasa_a_finalizada_con_todas_las_tareas_y_checklist_completos(): void
    {
        $ot = $this->crearOt(tareas: 2);
        $this->finalizarTodasLasTareas($ot);
        $this->completarChecklist($ot);

        app(EstadoOtService::class)->recalcular($ot->fresh());

        $this->assertSame('finalizada', $ot->fresh()->estado->slug);
        $this->assertNotNull($ot->fresh()->fecha_finalizacion);
    }

    public function test_finalizar_ultima_tarea_desde_el_componente_cierra_la_ot(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $this->completarChecklist($ot);
        $tarea = $ot->tareas()->first();
        $tecnicoUser = $tarea->tecnico->usuario;
        $tecnicoUser->assignRole(RolPrioridad::Tecnico->value);

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('planificar')->assertHasNoErrors();

        $comp = Volt::actingAs($tecnicoUser)
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot->fresh()]);
        $comp->call('iniciarTarea', $tarea->id)->assertHasNoErrors();
        $tarea->fresh()->update(['fecha_inicio' => now()->subHours(4)]);
        $this->adjuntarEvidenciaTarea($tarea->fresh());
        // Ya no se piden días al operario: los calcula el sistema por horas reales.
        $comp->call('finalizarTarea', $tarea->id)->assertHasNoErrors();

        $ot->refresh();
        $this->assertSame('finalizada', $ot->estado->slug);
        $this->assertEquals(0.5, (float) $tarea->fresh()->dias_trabajados); // 4 horas / jornada de 8h
    }

    public function test_la_observacion_al_finalizar_queda_guardada_en_la_tarea(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $this->completarChecklist($ot);
        $tarea = $ot->tareas()->first();
        $tecnicoUser = $tarea->tecnico->usuario;
        $tecnicoUser->assignRole(RolPrioridad::Tecnico->value);

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('planificar')->assertHasNoErrors();

        $comp = Volt::actingAs($tecnicoUser)
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot->fresh()]);
        $comp->call('iniciarTarea', $tarea->id)->assertHasNoErrors();
        $this->adjuntarEvidenciaTarea($tarea->fresh());
        $comp->set("tareaObservaciones.{$tarea->id}", 'Se reemplazó el filtro, quedó pendiente una segunda visita.')
            ->call('finalizarTarea', $tarea->id)
            ->assertHasNoErrors();

        $this->assertSame(
            'Se reemplazó el filtro, quedó pendiente una segunda visita.',
            $tarea->fresh()->observaciones
        );
    }

    public function test_finalizar_sin_observacion_no_falla_y_queda_nula(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $this->completarChecklist($ot);
        $tarea = $ot->tareas()->first();
        $tecnicoUser = $tarea->tecnico->usuario;
        $tecnicoUser->assignRole(RolPrioridad::Tecnico->value);

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('planificar')->assertHasNoErrors();

        $comp = Volt::actingAs($tecnicoUser)
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot->fresh()]);
        $comp->call('iniciarTarea', $tarea->id)->assertHasNoErrors();
        $this->adjuntarEvidenciaTarea($tarea->fresh());
        $comp->call('finalizarTarea', $tarea->id)->assertHasNoErrors();

        $this->assertNull($tarea->fresh()->observaciones);
    }

    public function test_no_se_finaliza_una_tarea_sin_imagen_de_evidencia(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tarea = $ot->tareas()->first();
        $tarea->update(['estado_tarea' => 'en_curso', 'fecha_inicio' => now()]);

        try {
            app(OrdenTrabajoService::class)->finalizarTareaOperario($tarea->fresh(), $this->jefeDeTaller());
            $this->fail('Debía exigir la imagen de evidencia.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('evidencia', $e->getMessage());
        }
        $this->assertSame('en_curso', $tarea->fresh()->estado_tarea);

        $this->adjuntarEvidenciaTarea($tarea->fresh());
        app(OrdenTrabajoService::class)->finalizarTareaOperario($tarea->fresh(), $this->jefeDeTaller());
        $this->assertSame('finalizada', $tarea->fresh()->estado_tarea);
    }

    public function test_los_dias_trabajados_los_calcula_el_sistema_al_finalizar(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tarea = $ot->tareas()->first();
        $tarea->update(['estado_tarea' => 'en_curso', 'fecha_inicio' => now()->subDays(3)]);
        $this->adjuntarEvidenciaTarea($tarea->fresh());

        app(OrdenTrabajoService::class)->finalizarTareaOperario($tarea->fresh(), $this->jefeDeTaller());

        // Inicio hace 3 días (72 horas) / jornada de 8h → 9 días trabajados.
        $this->assertEquals(9, (float) $tarea->fresh()->dias_trabajados);
    }

    public function test_una_tarea_de_dos_horas_se_costea_como_fraccion_del_dia_no_el_dia_completo(): void
    {
        $ot = $this->crearOt(tareas: 1); // técnico con sueldo 2.400.000 → valor día 80.000
        $tarea = $ot->tareas()->first();
        $tarea->update(['estado_tarea' => 'en_curso', 'fecha_inicio' => now()->subHours(2)]);
        $this->adjuntarEvidenciaTarea($tarea->fresh());

        app(OrdenTrabajoService::class)->finalizarTareaOperario($tarea->fresh(), $this->jefeDeTaller());

        $this->assertEquals(0.25, (float) $tarea->fresh()->dias_trabajados); // 2 horas / jornada de 8h

        $costeo = app(CosteoOtService::class)->calcular($ot->fresh());
        $this->assertEqualsWithDelta(20_000, $costeo['mano_obra_propia'], 0.01); // 0.25 × 80.000
    }

    public function test_comparativo_tiempo_estimado_vs_real(): void
    {
        $ot = $this->crearOt(['tiempo_estimado_dias' => 4], tareas: 2);
        $this->finalizarTodasLasTareas($ot, dias: 3); // 3 + 3 = 6 días reales

        $ot->refresh();
        $this->assertEquals(6.0, $ot->diasTrabajadosTotales());
        $this->assertEquals(2.0, $ot->desviacionDias()); // 6 - 4
    }

    public function test_estado_no_cambia_manualmente_desde_el_componente(): void
    {
        $ot = $this->crearOt();

        $comp = Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot]);

        // No existe propiedad/acción para fijar estado_id a mano.
        $this->assertFalse(method_exists($comp->instance(), 'setEstado'));
        $this->assertSame('en_revision', $ot->fresh()->estado->slug);
    }
}
