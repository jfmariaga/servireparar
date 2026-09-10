<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Enums\RolPrioridad;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Prioridad;
use App\Models\SolicitudInsumoOt;
use App\Models\Tecnico;
use App\Services\OrdenTrabajo\AtencionInsumoOtService;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 12 · Fase 12.4 — el insumo se entrega al encargado de la tarea
 * (`detalle_ot.tecnico_id`); Bodega no lo cambia (D14 / H28).
 */
class InsumoAlTecnicoTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    private function crearOtConInsumo(Tecnico $tecnico, Inventario $item): \App\Models\OrdenTrabajo
    {
        return app(OrdenTrabajoService::class)->crear($this->jefeDeTaller(), [
            'cliente_id' => Cliente::factory()->create()->id,
            'prioridad_id' => Prioridad::where('nombre', 'Media')->value('id'),
            'descripcion' => 'OT insumo al técnico',
            'tiempo_estimado_dias' => 3,
        ], [[
            'descripcion' => 'Cambiar rodamiento',
            'tecnico_id' => $tecnico->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 2]],
        ]]);
    }

    public function test_la_solicitud_lleva_el_tecnico_de_su_tarea(): void
    {
        $tec = Tecnico::factory()->conSueldo()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 20]);

        $ot = $this->crearOtConInsumo($tec, $item);

        $this->assertSame($tec->id, SolicitudInsumoOt::where('ot_id', $ot->id)->value('entregado_a_tecnico_id'));
    }

    public function test_reasignar_la_tarea_antes_de_entregar_cambia_el_destinatario(): void
    {
        $tec1 = Tecnico::factory()->conSueldo()->create();
        $tec2 = Tecnico::factory()->conSueldo()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 20]);
        $ot = $this->crearOtConInsumo($tec1, $item);
        $tarea = $ot->tareas()->first();

        app(OrdenTrabajoService::class)->actualizarTarea($tarea, $this->jefeDeTaller(), [
            'tecnico_id' => $tec2->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 2]],
        ]);

        $this->assertSame($tec2->id, SolicitudInsumoOt::where('ot_id', $ot->id)->value('entregado_a_tecnico_id'));
    }

    public function test_no_cambia_tras_entregada(): void
    {
        $tec1 = Tecnico::factory()->conSueldo()->create();
        $tec2 = Tecnico::factory()->conSueldo()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 20, 'costo_unitario' => 10]);
        $item->movimientos()->create(['tipo_mov' => 'entrada', 'cantidad' => 20, 'cantidad_disponible' => 20, 'costo_unitario' => 10, 'fecha' => now(), 'usuario_id' => $this->jefeDeTaller()->id, 'origen' => 'entrada_proveedor']);
        $ot = $this->crearOtConInsumo($tec1, $item);
        $solicitud = SolicitudInsumoOt::where('ot_id', $ot->id)->firstOrFail();

        app(AtencionInsumoOtService::class)->entregar($solicitud, $this->usuarioConRol(RolPrioridad::Almacenista->value));

        // Reasignar la tarea después de la entrega no debe mover una solicitud ya cerrada.
        app(OrdenTrabajoService::class)->actualizarTarea($ot->tareas()->first(), $this->jefeDeTaller(), [
            'tecnico_id' => $tec2->id,
            'insumos' => [['inventario_id' => $item->id, 'cantidad' => 2]],
        ]);

        $solicitud->refresh();
        $this->assertSame('entregada', $solicitud->estado);
        $this->assertSame($tec1->id, $solicitud->entregado_a_tecnico_id);
    }

    public function test_el_movimiento_de_salida_nombra_al_tecnico_destinatario(): void
    {
        $tecUser = \App\Models\User::factory()->create(['name' => 'Carlos Pérez']);
        $tec = Tecnico::factory()->conSueldo()->create(['usuario_id' => $tecUser->id]);
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 20, 'costo_unitario' => 10]);
        $item->movimientos()->create(['tipo_mov' => 'entrada', 'cantidad' => 20, 'cantidad_disponible' => 20, 'costo_unitario' => 10, 'fecha' => now(), 'usuario_id' => $this->jefeDeTaller()->id, 'origen' => 'entrada_proveedor']);
        $ot = $this->crearOtConInsumo($tec, $item);
        $solicitud = SolicitudInsumoOt::where('ot_id', $ot->id)->firstOrFail();

        app(AtencionInsumoOtService::class)->entregar($solicitud, $this->usuarioConRol(RolPrioridad::Almacenista->value));

        $mov = MovimientoInventario::where('id', $solicitud->fresh()->movimiento_id)->firstOrFail();
        $this->assertStringContainsString('Carlos Pérez', $mov->motivo);
    }
}
