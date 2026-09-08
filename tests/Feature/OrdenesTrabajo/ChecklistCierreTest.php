<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Services\OrdenTrabajo\EstadoOtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ChecklistCierreTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    public function test_checklist_incompleto_bloquea_el_cierre(): void
    {
        $ot = $this->crearOt(tareas: 2);
        $this->finalizarTodasLasTareas($ot);
        $ot->checklist()->create(['item' => 'Pruebas de funcionamiento']);
        $ot->checklist()->create(['item' => 'Limpieza final']);

        // Responder solo uno
        $primero = $ot->checklist()->first();

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('responderChecklist', $primero->id, true, app(EstadoOtService::class));

        $ot->refresh();
        $this->assertSame('en_curso', $ot->estado->slug);
        $this->assertFalse(app(EstadoOtService::class)->puedeFinalizar($ot));
    }

    public function test_completar_todo_el_checklist_habilita_y_cierra_la_ot(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $this->finalizarTodasLasTareas($ot);
        $ot->checklist()->create(['item' => 'Pruebas de funcionamiento']);
        $ot->checklist()->create(['item' => 'Limpieza final']);

        $comp = Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot]);

        foreach ($ot->checklist as $item) {
            $comp->call('responderChecklist', $item->id, true, app(EstadoOtService::class));
        }

        $ot->refresh();
        $this->assertTrue($ot->checklistCompleto());
        $this->assertSame('finalizada', $ot->estado->slug);
    }

    public function test_checklist_vacio_no_cuenta_como_completo(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $this->finalizarTodasLasTareas($ot);

        app(EstadoOtService::class)->recalcular($ot->fresh());

        // Sin ítems de checklist, checklistCompleto() es false → no finaliza.
        $this->assertFalse($ot->fresh()->checklistCompleto());
        $this->assertSame('en_curso', $ot->fresh()->estado->slug);
    }

    public function test_agregar_item_de_checklist_desde_el_componente(): void
    {
        $ot = $this->crearOt();

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->set('nuevoItem', 'Verificar torque de tornillería')
            ->call('agregarItemChecklist')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('checklist_ot', ['ot_id' => $ot->id, 'item' => 'Verificar torque de tornillería', 'cumple' => null]);
    }
}
