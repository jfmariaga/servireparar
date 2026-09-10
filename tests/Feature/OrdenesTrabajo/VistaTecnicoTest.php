<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Models\Tecnico;
use App\Models\User;
use App\Services\OrdenTrabajo\EstadoOtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Phase 13 · D19 — el técnico solo ve sus tareas; en el detalle de la OT no ve
 * checklist, trazabilidad ni enlaces a Bodega/herramientas; su dashboard muestra
 * las tareas asignadas / en curso / atrasadas.
 */
class VistaTecnicoTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    /** @return array{0: User, 1: Tecnico} */
    private function tecnico(): array
    {
        $user = $this->tecnicoUser();
        $tec = Tecnico::factory()->conSueldo()->create(['usuario_id' => $user->id]);

        return [$user, $tec];
    }

    public function test_el_tecnico_solo_ve_sus_tareas_en_el_detalle(): void
    {
        [$user, $tec] = $this->tecnico();
        $ot = $this->crearOt(tareas: 2);
        $tareas = $ot->tareas()->orderBy('id')->get();
        $tareas[0]->update(['tecnico_id' => $tec->id, 'descripcion' => 'Tarea del técnico']);
        // $tareas[1] queda con otro técnico.

        $comp = Volt::actingAs($user)->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot]);

        $comp->assertViewHas('vistaTecnico', true)
            ->assertSee('Mis tareas')
            ->assertSee('Tarea del técnico')
            ->assertDontSee($tareas[1]->descripcion)
            ->assertDontSee('Checklist de cierre')
            ->assertDontSee('Trazabilidad')
            ->assertDontSee('Ver en Bodega');
    }

    public function test_el_jefe_ve_la_vista_completa(): void
    {
        $ot = $this->crearOt(tareas: 2);

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->assertViewHas('vistaTecnico', false)
            ->assertSee('Checklist de cierre')
            ->assertSee('Trazabilidad');
    }

    public function test_el_dashboard_del_tecnico_agrupa_sus_tareas(): void
    {
        [$user, $tec] = $this->tecnico();
        $ot = $this->crearOt(tareas: 3);
        app(EstadoOtService::class)->liberar($ot, $this->jefeDeTaller());

        $tareas = $ot->tareas()->orderBy('id')->get();
        // Asignada
        $tareas[0]->update(['tecnico_id' => $tec->id, 'dias_cumplimiento' => 3]);
        // En curso
        $tareas[1]->update(['tecnico_id' => $tec->id, 'estado_tarea' => 'en_curso', 'fecha_inicio' => now()]);
        // Atrasada (en curso + plazo vencido)
        $tareas[2]->update(['tecnico_id' => $tec->id, 'estado_tarea' => 'en_curso', 'fecha_inicio' => now()->subDays(9), 'dias_cumplimiento' => 2]);

        $comp = Volt::actingAs($user)->test('dashboard');

        $comp->assertSee('Mis tareas')
            ->assertViewHas('asignadas', fn ($c) => $c->count() === 1)
            ->assertViewHas('enCurso', fn ($c) => $c->count() === 2)
            ->assertViewHas('atrasadas', fn ($c) => $c->count() === 1);
    }

    public function test_el_dashboard_no_muestra_tareas_de_ot_en_planificacion(): void
    {
        [$user, $tec] = $this->tecnico();
        $ot = $this->crearOt(tareas: 1); // sin liberar
        $ot->tareas()->update(['tecnico_id' => $tec->id]);

        Volt::actingAs($user)->test('dashboard')
            ->assertViewHas('asignadas', fn ($c) => $c->isEmpty());
    }
}
