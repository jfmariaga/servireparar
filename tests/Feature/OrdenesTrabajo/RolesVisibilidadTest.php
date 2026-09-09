<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Enums\RolPrioridad;
use App\Models\OrdenTrabajo;
use App\Models\Tecnico;
use App\Models\User;
use App\Services\OrdenTrabajo\EstadoOtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Phase 11 / Fase 7 — visibilidad de OT por rol (D7), permiso propio de Bodega
 * para atender insumos (H18) y checklist de cierre por defecto (H16).
 */
class RolesVisibilidadTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    public function test_el_tecnico_solo_ve_las_ot_donde_tiene_tareas(): void
    {
        $propia = $this->crearOt(tareas: 1);
        $ajena = $this->crearOt(tareas: 1);

        $tecnicoUser = $this->usuarioConRol(RolPrioridad::Tecnico->value);
        $tecnico = Tecnico::factory()->conSueldo()->create(['usuario_id' => $tecnicoUser->id]);
        $propia->tareas()->update(['tecnico_id' => $tecnico->id]);

        $visibles = OrdenTrabajo::visiblesPara($tecnicoUser)->pluck('id');
        $this->assertTrue($visibles->contains($propia->id));
        $this->assertFalse($visibles->contains($ajena->id));

        // El Jefe de Taller las ve todas.
        $todas = OrdenTrabajo::visiblesPara($this->jefeDeTaller())->pluck('id');
        $this->assertTrue($todas->contains($propia->id) && $todas->contains($ajena->id));
    }

    public function test_solo_bodega_atiende_las_solicitudes_de_insumo(): void
    {
        $sinPermiso = $this->usuarioConRol(RolPrioridad::Vendedor->value);
        Volt::actingAs($sinPermiso)->test('inventario.solicitudes-ot')->assertForbidden();

        $almacen = User::factory()->create(['estado' => 'activo']);
        $almacen->assignRole(RolPrioridad::Almacenista->value);
        $this->assertTrue($almacen->can('attend-ot-insumo'));
        Volt::actingAs($almacen)->test('inventario.solicitudes-ot')->assertOk();
    }

    public function test_la_ot_nace_con_el_checklist_de_cierre_por_defecto(): void
    {
        config(['ot.checklist_por_defecto' => ['Ítem A', 'Ítem B']]);
        $ot = $this->crearOt(tareas: 1);

        $this->assertSame(2, $ot->checklist()->count());
        $this->assertDatabaseHas('checklist_ot', ['ot_id' => $ot->id, 'item' => 'Ítem A', 'cumple' => null]);

        // Con las tareas listas pero el checklist sin responder, la OT no finaliza.
        $this->finalizarTodasLasTareas($ot);
        app(EstadoOtService::class)->recalcular($ot->fresh());
        $this->assertFalse(app(EstadoOtService::class)->puedeFinalizar($ot->fresh()));
    }
}
