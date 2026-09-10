<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Services\OrdenTrabajo\EstadoOtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Phase 12 · Fase 12.1 — trazabilidad en orden cronológico (FR-019) y checklist de
 * cierre que solo se puede responder cuando todas las tareas están finalizadas (D9 / FR-007).
 */
class TrazabilidadChecklistPhase12Test extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    public function test_la_bitacora_se_lista_en_orden_cronologico_ascendente(): void
    {
        $ot = $this->crearOt(tareas: 1);

        $ot->registrarEvento('correccion', 'Ajuste posterior', $this->jefeDeTaller());

        $tipos = $ot->fresh()->eventos->pluck('tipo')->all();

        $this->assertSame('creacion', $tipos[0], 'La creación de la OT debe ser el primer evento.');
        $this->assertSame('correccion', $tipos[array_key_last($tipos)], 'El evento más reciente va al final.');
    }

    public function test_no_se_puede_responder_el_checklist_con_tareas_sin_finalizar(): void
    {
        $ot = $this->crearOt(tareas: 2);
        $item = $ot->checklist()->create(['item' => 'Pruebas de funcionamiento']);

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('responderChecklist', $item->id, true, app(EstadoOtService::class));

        $this->assertNull($item->fresh()->cumple, 'El checklist no debe aceptar respuestas antes de finalizar todas las tareas.');
    }

    public function test_el_checklist_se_puede_responder_con_todas_las_tareas_finalizadas(): void
    {
        $ot = $this->crearOt(tareas: 2);
        $this->finalizarTodasLasTareas($ot);
        $item = $ot->checklist()->create(['item' => 'Pruebas de funcionamiento']);

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('responderChecklist', $item->id, true, app(EstadoOtService::class));

        $this->assertTrue((bool) $item->fresh()->cumple);
    }

    public function test_tareas_canceladas_no_bloquean_el_checklist(): void
    {
        $ot = $this->crearOt(tareas: 2);
        $this->finalizarTodasLasTareas($ot);
        // Una tarea adicional cancelada no debe impedir responder el checklist.
        $cancelada = $ot->tareas()->first()->replicate();
        $cancelada->forceFill(['estado_tarea' => 'cancelada'])->save();
        $item = $ot->checklist()->create(['item' => 'Limpieza final']);

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('responderChecklist', $item->id, true, app(EstadoOtService::class));

        $this->assertTrue((bool) $item->fresh()->cumple);
    }
}
