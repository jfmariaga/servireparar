<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Models\Cliente;
use App\Models\Contratista;
use App\Models\Inventario;
use App\Models\Prioridad;
use App\Models\Tecnico;
use App\Services\OrdenTrabajo\CosteoOtService;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CosteoUtilidadTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    /**
     * Caso tomado como oráculo del formato Excel real (SC-005):
     *  - Mano de obra propia: 3 días × $100.000 + 2 días × $80.000 = $460.000
     *  - Contratistas: $500.000 + $150.000 = $650.000
     *  - Repuestos: 7 × $7.000 + 1 × $12.000 = $61.000
     *  - Costo total: $1.171.000 ; Valor proyecto: $2.000.000 ; Utilidad: $829.000
     */
    public function test_costo_total_y_utilidad_coinciden_con_el_oraculo(): void
    {
        $cliente = Cliente::factory()->create();
        $tecnicoA = Tecnico::factory()->conSueldo(3_000_000)->create(); // valor día 100.000
        $tecnicoB = Tecnico::factory()->conSueldo(2_400_000)->create(); // valor día 80.000
        $disco = Inventario::factory()->create(['tipo' => 'consumible', 'costo_unitario' => 7_000, 'stock_actual' => 100]);
        $carboflap = Inventario::factory()->create(['tipo' => 'consumible', 'costo_unitario' => 12_000, 'stock_actual' => 100]);

        $ot = app(OrdenTrabajoService::class)->crear($this->jefeDeTaller(), [
            'cliente_id' => $cliente->id,
            'prioridad_id' => Prioridad::where('nombre', 'Media')->value('id'),
            'descripcion' => 'Desmantelar escalera',
            'tiempo_estimado_dias' => 5,
            'valor_proyecto' => 2_000_000,
        ], [
            ['descripcion' => 'Corte', 'tecnico_id' => $tecnicoA->id, 'insumo_id' => $disco->id, 'cantidad_insumo' => 7],
            ['descripcion' => 'Pulido', 'tecnico_id' => $tecnicoB->id, 'insumo_id' => $carboflap->id, 'cantidad_insumo' => 1],
        ]);

        $tareas = $ot->tareas()->orderBy('id')->get();
        $tareas[0]->update(['estado_tarea' => 'finalizada', 'dias_trabajados' => 3, 'fecha_fin' => now()]);
        $tareas[1]->update(['estado_tarea' => 'finalizada', 'dias_trabajados' => 2, 'fecha_fin' => now()]);

        $ballestas = Contratista::factory()->create();
        $otro = Contratista::factory()->create();
        $ot->manoObraContratistas()->create(['contratista_id' => $ballestas->id, 'especialidad' => 'Calcomanías', 'cantidad' => 1, 'valor' => 500_000]);
        $ot->manoObraContratistas()->create(['contratista_id' => $otro->id, 'cantidad' => 1, 'valor' => 150_000]);

        $costeo = app(CosteoOtService::class)->calcular($ot->fresh());

        $this->assertEqualsWithDelta(460_000, $costeo['mano_obra_propia'], 0.01);
        $this->assertEqualsWithDelta(650_000, $costeo['contratistas'], 0.01);
        $this->assertEqualsWithDelta(61_000, $costeo['repuestos'], 0.01);
        $this->assertEqualsWithDelta(1_171_000, $costeo['costo_total'], 0.01);
        $this->assertEqualsWithDelta(829_000, $costeo['utilidad_neta'], 0.01);
    }

    public function test_utilidad_neta_con_valor_proyecto_nulo_es_negativa_del_costo_total(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $ot->tareas()->update(['estado_tarea' => 'finalizada', 'dias_trabajados' => 1]); // sueldo 2.4M → 80.000

        $costeo = app(CosteoOtService::class)->calcular($ot->fresh());

        $this->assertNull($costeo['valor_proyecto']);
        $this->assertEqualsWithDelta(80_000, $costeo['costo_total'], 0.01);
        $this->assertEqualsWithDelta(-80_000, $costeo['utilidad_neta'], 0.01);
    }

    public function test_ot_cerrada_no_se_recostea_si_el_sueldo_sube_despues(): void
    {
        $cierre = now()->subDays(10);

        $tecnico = Tecnico::factory()->conSueldo(3_000_000, $cierre->copy()->subMonths(3)->toDateString())->create();

        $ot = $this->crearOt(tareas: 1);
        $tarea = $ot->tareas()->first();
        $tarea->update(['tecnico_id' => $tecnico->id, 'estado_tarea' => 'finalizada', 'dias_trabajados' => 4, 'fecha_fin' => $cierre]);
        $ot->update(['estado_id' => \App\Models\EstadoOt::idPorSlug('finalizada'), 'fecha_finalizacion' => $cierre]);

        $costeoAntes = app(CosteoOtService::class)->calcular($ot->fresh());
        $this->assertEqualsWithDelta(400_000, $costeoAntes['mano_obra_propia'], 0.01); // 4 × 100.000

        // El sueldo sube DESPUÉS del cierre.
        $tecnico->registrarSueldo(6_000_000, $cierre->copy()->addDay());

        $costeoDespues = app(CosteoOtService::class)->calcular($ot->fresh());
        $this->assertEqualsWithDelta(400_000, $costeoDespues['mano_obra_propia'], 0.01, 'Una OT cerrada no se recostea');
    }

    public function test_panel_de_costeo_solo_visible_para_administrador(): void
    {
        $ot = $this->crearOt();

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.costeo', ['ordenTrabajo' => $ot])
            ->assertForbidden();

        Volt::actingAs($this->administrador())
            ->test('ordenes-trabajo.costeo', ['ordenTrabajo' => $ot])
            ->assertOk();
    }

    public function test_administrador_agrega_mano_de_obra_de_contratista_desde_el_panel(): void
    {
        $ot = $this->crearOt();
        $contratista = Contratista::factory()->create();

        Volt::actingAs($this->administrador())
            ->test('ordenes-trabajo.costeo', ['ordenTrabajo' => $ot])
            ->set('contratistaId', $contratista->id)
            ->set('especialidad', 'Soldadura')
            ->set('cantidad', '2')
            ->set('valor', '300000')
            ->call('agregarContratista')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ot_mano_obra_contratista', [
            'ot_id' => $ot->id,
            'contratista_id' => $contratista->id,
            'valor' => 300000,
        ]);
    }
}
