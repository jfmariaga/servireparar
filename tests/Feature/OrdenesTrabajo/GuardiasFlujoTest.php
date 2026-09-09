<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Models\Inventario;
use App\Models\SolicitudInsumoOt;
use App\Models\Tecnico;
use App\Services\OrdenTrabajo\EstadoOtService;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use App\Services\OrdenTrabajo\OtHerramientaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Phase 11 / Fase 5 — guardias de la máquina de estados: planificación obligatoria
 * (H6), confirmación del Jefe para finalizar tareas con insumos sin entregar (D3),
 * reapertura invalida la salida aprobada (H7), cancelación de OT / tarea (D8).
 */
class GuardiasFlujoTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    public function test_no_se_ejecuta_una_ot_sin_planificar(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tarea = $ot->tareas()->first();
        $tecnico = $this->tecnicoUser();

        $this->assertTrue($tecnico->cannot('executeTareas', $ot->fresh(['estado'])));

        app(EstadoOtService::class)->planificar($ot, $this->jefeDeTaller());
        $this->assertSame('pendiente', $ot->fresh()->estado->slug);
        $this->assertTrue($tecnico->can('executeTareas', $ot->fresh(['estado'])));
    }

    public function test_finalizar_tarea_con_insumo_sin_entregar_espera_al_jefe(): void
    {
        $ot = $this->crearOt(tareas: 1);
        app(EstadoOtService::class)->planificar($ot, $this->jefeDeTaller());
        $tecnico = Tecnico::factory()->conSueldo()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 50]);

        $tarea = app(OrdenTrabajoService::class)->agregarTarea($ot->fresh(['estado']), $this->jefeDeTaller(), [
            'descripcion' => 'Con insumo',
            'tecnico_id' => $tecnico->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 2]],
        ]);
        $tarea->update(['estado_tarea' => 'en_curso', 'fecha_inicio' => now()]);

        // Insumo aún pendiente en Bodega → no finaliza, queda a la espera.
        $tarea = app(OrdenTrabajoService::class)->marcarTareaListaParaFinalizar($tarea->fresh(), $this->jefeDeTaller(), 2);
        $this->assertSame('en_curso', $tarea->estado_tarea);
        $this->assertTrue($tarea->finalizacionPendiente());

        // El Jefe confirma.
        app(OrdenTrabajoService::class)->confirmarFinalizacionTarea($tarea->fresh(), $this->jefeDeTaller());
        $this->assertSame('finalizada', $tarea->fresh()->estado_tarea);
        $this->assertNull($tarea->fresh()->finalizacion_solicitada_en);
    }

    public function test_finalizar_tarea_con_insumo_rechazado_tambien_espera_al_jefe(): void
    {
        $ot = $this->crearOt(tareas: 1);
        app(EstadoOtService::class)->planificar($ot, $this->jefeDeTaller());
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 50]);

        $tarea = app(OrdenTrabajoService::class)->agregarTarea($ot->fresh(['estado']), $this->jefeDeTaller(), [
            'descripcion' => 'Con insumo rechazado',
            'tecnico_id' => Tecnico::factory()->conSueldo()->create()->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 2]],
        ]);
        SolicitudInsumoOt::where('detalle_ot_id', $tarea->id)->update(['estado' => 'rechazada', 'motivo_rechazo' => 'Se compra directo']);
        $tarea->update(['estado_tarea' => 'en_curso', 'fecha_inicio' => now()]);

        $tarea = app(OrdenTrabajoService::class)->marcarTareaListaParaFinalizar($tarea->fresh(), $this->jefeDeTaller(), 1);
        $this->assertTrue($tarea->finalizacionPendiente(), 'Un insumo rechazado también retiene la finalización');
    }

    public function test_finalizar_tarea_sin_insumos_es_directo(): void
    {
        $ot = $this->crearOt(tareas: 1);
        app(EstadoOtService::class)->planificar($ot, $this->jefeDeTaller());
        $tarea = $ot->tareas()->first();
        $tarea->update(['estado_tarea' => 'en_curso', 'fecha_inicio' => now()]);

        $tarea = app(OrdenTrabajoService::class)->marcarTareaListaParaFinalizar($tarea->fresh(), $this->jefeDeTaller(), 1);
        $this->assertSame('finalizada', $tarea->estado_tarea);
    }

    public function test_reabrir_una_ot_finalizada_invalida_la_salida_aprobada(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $ot->update(['valor_proyecto' => 500_000, 'salida_estado' => 'aprobada']);
        $this->finalizarTodasLasTareas($ot);
        $this->completarChecklist($ot);
        app(EstadoOtService::class)->recalcular($ot->fresh());
        $this->assertSame('finalizada', $ot->fresh()->estado->slug);

        app(EstadoOtService::class)->reabrir($ot->fresh(['estado']), $this->jefeDeTaller(), 'Trabajo adicional');

        $ot->refresh();
        $this->assertSame('en_curso', $ot->estado->slug);
        $this->assertSame('no_solicitada', $ot->salida_estado);
        $this->assertDatabaseHas('ot_eventos', ['ot_id' => $ot->id, 'tipo' => 'correccion', 'descripcion' => 'Salida de equipo (aprobada) invalidada: la OT volvió a ejecución.']);
    }

    public function test_cancelar_tarea_libera_insumos_y_recalcula(): void
    {
        $ot = $this->crearOt(tareas: 2);
        $tecnico = Tecnico::factory()->conSueldo()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 50]);
        $tarea = app(OrdenTrabajoService::class)->agregarTarea($ot, $this->jefeDeTaller(), [
            'descripcion' => 'A cancelar',
            'tecnico_id' => $tecnico->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 4]],
        ]);

        app(OrdenTrabajoService::class)->cancelarTarea($tarea->fresh(), $this->jefeDeTaller(), 'Cliente desistió de esta parte');

        $this->assertSame('cancelada', $tarea->fresh()->estado_tarea);
        $this->assertSame('cancelada', SolicitudInsumoOt::where('detalle_ot_id', $tarea->id)->value('estado'));
        $this->assertEquals(50, $item->fresh()->disponible(), 'La reserva se libera');
    }

    public function test_no_se_cancela_la_unica_tarea_activa(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $this->expectException(ValidationException::class);
        app(OrdenTrabajoService::class)->cancelarTarea($ot->tareas()->first(), $this->jefeDeTaller(), 'motivo');
    }

    public function test_cancelar_la_ot_la_cierra_y_libera_reservas(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tecnico = Tecnico::factory()->conSueldo()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 20]);
        app(OrdenTrabajoService::class)->agregarTarea($ot, $this->jefeDeTaller(), [
            'descripcion' => 'X', 'tecnico_id' => $tecnico->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 5]],
        ]);

        app(OrdenTrabajoService::class)->cancelarOt($ot->fresh(['estado']), $this->jefeDeTaller(), 'Proyecto anulado');

        $ot->refresh();
        $this->assertSame('cancelada', $ot->estado->slug);
        $this->assertTrue($ot->estado->es_terminal);
        $this->assertEquals(20, $item->fresh()->disponible());
        $this->assertSame(0, SolicitudInsumoOt::where('ot_id', $ot->id)->where('estado', 'pendiente')->count());
    }

    public function test_no_se_cancela_la_ot_con_herramientas_sin_devolver(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tool = Inventario::factory()->herramienta()->create();
        app(OtHerramientaService::class)->asignar($ot, $tool, $this->jefeDeTaller());

        try {
            app(OrdenTrabajoService::class)->cancelarOt($ot->fresh(['estado']), $this->jefeDeTaller(), 'motivo');
            $this->fail('Debía exigir devolver las herramientas.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('herramientas', $e->getMessage());
        }

        $this->assertNotSame('cancelada', $ot->fresh()->estado->slug);
    }
}
