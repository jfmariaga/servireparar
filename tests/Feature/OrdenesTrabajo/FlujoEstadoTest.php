<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Services\OrdenTrabajo\EstadoOtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
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

        $comp = Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot]);

        $comp->call('iniciarTarea', $tarea->id)->assertHasNoErrors();
        $comp->call('confirmarFinalizarTarea', $tarea->id)
            ->set('diasTrabajados', '3')
            ->call('finalizarTarea')
            ->assertHasNoErrors();

        $ot->refresh();
        $this->assertSame('finalizada', $ot->estado->slug);
        $this->assertEquals(3, (float) $tarea->fresh()->dias_trabajados);
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
