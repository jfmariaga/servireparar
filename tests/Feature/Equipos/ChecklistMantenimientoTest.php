<?php

namespace Tests\Feature\Equipos;

use App\Enums\RolPrioridad;
use App\Models\ChecklistMantenimiento;
use App\Models\Equipo;
use App\Services\OrdenTrabajo\EstadoOtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\Feature\OrdenesTrabajo\OtScenario;
use Tests\TestCase;

/**
 * Spec 005, US4 (FR-007): checklist técnico digital de mantenimiento —
 * plantilla única genérica, precargada por tarea al crear una OT con equipo
 * asociado, respondida por el técnico mientras ejecuta su tarea (mismo
 * criterio que las variables técnicas) y visible en el historial del equipo.
 */
class ChecklistMantenimientoTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    public function test_crear_ot_con_equipo_precarga_el_checklist_tecnico_por_tarea(): void
    {
        $equipo = Equipo::factory()->create();

        $ot = $this->crearOt(['equipo_id' => $equipo->id]);
        $tarea = $ot->tareas->first();

        $items = (array) config('equipos.checklist_tecnico_por_defecto', []);
        $this->assertNotEmpty($items);
        $this->assertSame(count($items), ChecklistMantenimiento::where('detalle_ot_id', $tarea->id)->count());
        $this->assertSame(count($items), ChecklistMantenimiento::where('ot_id', $ot->id)->whereNull('cumple')->count());
    }

    public function test_ot_sin_equipo_no_genera_checklist_tecnico(): void
    {
        $ot = $this->crearOt(['equipo_id' => null]);

        $this->assertSame(0, ChecklistMantenimiento::where('ot_id', $ot->id)->count());
    }

    public function test_tecnico_responde_el_checklist_tecnico_al_ejecutar_la_tarea(): void
    {
        $equipo = Equipo::factory()->create();
        $ot = $this->crearOt(['equipo_id' => $equipo->id]);
        app(EstadoOtService::class)->liberar($ot, $this->jefeDeTaller());
        $tarea = $ot->tareas->first();
        $tarea->update(['estado_tarea' => 'en_curso']);
        $tarea->tecnico->usuario->assignRole(RolPrioridad::Tecnico->value);
        $item = ChecklistMantenimiento::where('detalle_ot_id', $tarea->id)->first();

        Volt::actingAs($tarea->tecnico->usuario)
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot->fresh()])
            ->call('responderChecklistTecnico', $tarea->id, $item->id, true)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('checklist_mantenimiento', [
            'id' => $item->id,
            'cumple' => true,
            'respondido_por' => $tarea->tecnico->usuario->id,
        ]);
    }

    public function test_no_se_puede_responder_una_tarea_que_no_esta_en_curso(): void
    {
        $equipo = Equipo::factory()->create();
        $ot = $this->crearOt(['equipo_id' => $equipo->id]);
        app(EstadoOtService::class)->liberar($ot, $this->jefeDeTaller());
        $tarea = $ot->tareas->first();
        $tarea->update(['estado_tarea' => 'pendiente']);
        $tarea->tecnico->usuario->assignRole(RolPrioridad::Tecnico->value);
        $item = ChecklistMantenimiento::where('detalle_ot_id', $tarea->id)->first();

        Volt::actingAs($tarea->tecnico->usuario)
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot->fresh()])
            ->call('responderChecklistTecnico', $tarea->id, $item->id, true);

        $this->assertNull($item->fresh()->cumple);
    }

    public function test_el_resultado_queda_visible_en_el_historial_del_equipo(): void
    {
        $equipo = Equipo::factory()->create();
        $ot = $this->crearOt(['equipo_id' => $equipo->id]);
        $tarea = $ot->tareas->first();
        ChecklistMantenimiento::where('detalle_ot_id', $tarea->id)->first()->update(['cumple' => true]);

        Volt::actingAs($this->administrador())
            ->test('equipos.historial', ['equipo' => $equipo])
            ->assertSee('Checklist técnico')
            ->assertSee((array) config('equipos.checklist_tecnico_por_defecto'));
    }
}
