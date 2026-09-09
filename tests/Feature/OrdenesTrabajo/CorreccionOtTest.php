<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Models\Tecnico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CorreccionOtTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    public function test_reasignar_tecnico_queda_registrado_en_la_bitacora(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tarea = $ot->tareas()->first();
        $nuevoTecnico = Tecnico::factory()->conSueldo()->create();

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('editarTarea', $tarea->id)
            ->set('tareaForm.descripcion', $tarea->descripcion)
            ->set('tareaForm.tecnico_id', $nuevoTecnico->id)
            ->call('guardarTarea')
            ->assertHasNoErrors();

        $this->assertSame($nuevoTecnico->id, $tarea->fresh()->tecnico_id);
        $this->assertDatabaseHas('ot_eventos', ['ot_id' => $ot->id, 'tipo' => 'correccion']);
    }

    public function test_una_tarea_finalizada_no_se_puede_editar_ni_quitar(): void
    {
        $ot = $this->crearOt(tareas: 2);
        $tarea = $ot->tareas()->first();
        $tarea->update(['estado_tarea' => 'finalizada', 'dias_trabajados' => 2, 'fecha_fin' => now()]);
        $otroTecnico = Tecnico::factory()->conSueldo()->create();

        $comp = Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot]);

        // Editar: el componente ni siquiera abre el formulario.
        $comp->call('editarTarea', $tarea->id);
        $this->assertNull($comp->get('editandoTareaId'));

        // Aunque se fuerce el guardado apuntando a la tarea finalizada, no cambia.
        $comp->set('editandoTareaId', $tarea->id)
            ->set('tareaForm', ['descripcion' => 'Hackeada', 'tecnico_id' => $otroTecnico->id, 'insumos' => []])
            ->call('guardarTarea');
        $this->assertSame('finalizada', $tarea->fresh()->estado_tarea);
        $this->assertNotSame('Hackeada', $tarea->fresh()->descripcion);
        $this->assertNotSame($otroTecnico->id, $tarea->fresh()->tecnico_id);

        // Quitar: bloqueado (solo tareas pendientes).
        $comp->call('quitarTarea', $tarea->id);
        $this->assertDatabaseHas('detalle_ot', ['id' => $tarea->id]);
    }

    public function test_corregir_descripcion_y_prioridad_no_altera_tareas_completadas(): void
    {
        $ot = $this->crearOt(tareas: 2);
        $this->finalizarTodasLasTareas($ot, dias: 2);
        $prioridadAlta = \App\Models\Prioridad::where('nombre', 'Alta')->value('id');

        Volt::actingAs($this->administrador())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('editarCabecera')
            ->set('descripcion', 'Descripción corregida por el Administrador')
            ->set('prioridadId', $prioridadAlta)
            ->call('guardarCabecera')
            ->assertHasNoErrors();

        $ot->refresh();
        $this->assertSame('Descripción corregida por el Administrador', $ot->descripcion);
        $this->assertSame($prioridadAlta, $ot->prioridad_id);
        // Las tareas finalizadas conservan sus días trabajados.
        $this->assertEquals(4.0, $ot->diasTrabajadosTotales());
        $this->assertDatabaseHas('ot_eventos', ['ot_id' => $ot->id, 'tipo' => 'correccion']);
    }

    public function test_el_tecnico_no_puede_corregir_la_cabecera(): void
    {
        $ot = $this->crearOt();

        Volt::actingAs($this->tecnicoUser())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('editarCabecera')
            ->assertForbidden();
    }

    public function test_jefe_de_taller_agrega_una_tarea_a_una_ot_existente(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tecnico = \App\Models\Tecnico::factory()->conSueldo()->create();

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('nuevaTarea')
            ->set('tareaForm.descripcion', 'Tarea agregada después')
            ->set('tareaForm.tecnico_id', $tecnico->id)
            ->call('guardarTarea')
            ->assertHasNoErrors();

        $this->assertSame(2, $ot->fresh()->tareas()->count());
        $this->assertDatabaseHas('detalle_ot', ['ot_id' => $ot->id, 'descripcion' => 'Tarea agregada después']);
        $this->assertDatabaseHas('ot_eventos', ['ot_id' => $ot->id, 'tipo' => 'correccion']);
    }

    public function test_agregar_tarea_con_insumo_genera_solicitud_hacia_bodega(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tecnico = \App\Models\Tecnico::factory()->conSueldo()->create();
        $insumo = \App\Models\Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 20]);

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('nuevaTarea')
            ->set('tareaForm.descripcion', 'Cambio de filtro')
            ->set('tareaForm.tecnico_id', $tecnico->id)
            ->set('tareaForm.insumos', [['inventario_id' => $insumo->id, 'cantidad' => '3']])
            ->call('guardarTarea')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('solicitudes_insumo_ot', [
            'ot_id' => $ot->id,
            'inventario_id' => $insumo->id,
            'estado' => 'pendiente',
        ]);
    }

    public function test_quitar_una_tarea_pendiente_deja_traza_y_no_permite_dejar_la_ot_sin_tareas(): void
    {
        $ot = $this->crearOt(tareas: 2);
        $tareaId = $ot->tareas()->first()->id;

        $comp = Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot]);

        $comp->call('quitarTarea', $tareaId)->assertHasNoErrors();
        $this->assertSame(1, $ot->fresh()->tareas()->count());
        $this->assertDatabaseMissing('detalle_ot', ['id' => $tareaId]);

        // Ya solo queda una: no se puede quitar.
        $comp->call('quitarTarea', $ot->fresh()->tareas()->first()->id);
        $this->assertSame(1, $ot->fresh()->tareas()->count());
    }

    public function test_no_se_puede_quitar_una_tarea_en_curso(): void
    {
        $ot = $this->crearOt(tareas: 2);
        $tarea = $ot->tareas()->first();
        $tarea->update(['estado_tarea' => 'en_curso', 'fecha_inicio' => now()]);

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('quitarTarea', $tarea->id);

        $this->assertDatabaseHas('detalle_ot', ['id' => $tarea->id]);
    }

    public function test_no_se_puede_agregar_tarea_a_una_ot_entregada(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $ot->update(['estado_id' => \App\Models\EstadoOt::idPorSlug('entregada')]);
        $tecnico = \App\Models\Tecnico::factory()->conSueldo()->create();

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('nuevaTarea')
            ->assertForbidden();
    }
}
