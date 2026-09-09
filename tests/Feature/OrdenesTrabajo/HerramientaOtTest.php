<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Models\Inventario;
use App\Models\OtHerramienta;
use App\Models\Tecnico;
use App\Services\OrdenTrabajo\CosteoOtService;
use App\Services\OrdenTrabajo\EstadoOtService;
use App\Services\OrdenTrabajo\OtHerramientaService;
use App\Services\OrdenTrabajo\SalidaEquipoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 11 / D4 — herramientas de inventario asignadas a una OT: salen del
 * almacén como `en_uso` y deben devolverse antes de solicitar la salida del equipo.
 */
class HerramientaOtTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    private function herramienta(string $estado = 'disponible'): Inventario
    {
        return Inventario::factory()->herramienta()->create(['estado_herramienta' => $estado, 'nombre' => 'Torquímetro']);
    }

    public function test_asignar_saca_la_herramienta_del_almacen_y_deja_traza(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tool = $this->herramienta();

        app(OtHerramientaService::class)->asignar($ot, $tool, $this->jefeDeTaller());

        $this->assertSame('en_uso', $tool->fresh()->estado_herramienta);
        $this->assertDatabaseHas('ot_herramientas', ['ot_id' => $ot->id, 'inventario_id' => $tool->id, 'devuelta_en' => null]);
        $this->assertDatabaseHas('ot_eventos', ['ot_id' => $ot->id, 'tipo' => 'herramienta_asignada']);
        $this->assertDatabaseHas('movimientos_inventario', ['inventario_id' => $tool->id, 'tipo_mov' => 'salida', 'origen' => 'ot']);
    }

    public function test_no_se_asigna_una_herramienta_no_disponible(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tool = $this->herramienta('en_mantenimiento');

        $this->expectException(ValidationException::class);
        app(OtHerramientaService::class)->asignar($ot, $tool, $this->jefeDeTaller());
    }

    public function test_devolver_reingresa_la_herramienta_con_estado_explicito(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $tool = $this->herramienta();
        $asig = app(OtHerramientaService::class)->asignar($ot, $tool, $this->jefeDeTaller());

        app(OtHerramientaService::class)->devolver($asig->fresh(), $this->jefeDeTaller(), 'dañada');

        $asig->refresh();
        $this->assertNotNull($asig->devuelta_en);
        $this->assertSame('dañada', $asig->estado_devolucion);
        $this->assertSame('dañada', $tool->fresh()->estado_herramienta);
        $this->assertDatabaseHas('ot_eventos', ['ot_id' => $ot->id, 'tipo' => 'herramienta_devuelta']);
    }

    public function test_no_se_solicita_la_salida_con_herramientas_sin_devolver(): void
    {
        $ot = $this->crearOt(tareas: 1);
        $ot->update(['valor_proyecto' => 500_000]);
        $tool = $this->herramienta();
        $asig = app(OtHerramientaService::class)->asignar($ot, $tool, $this->jefeDeTaller());

        $this->finalizarTodasLasTareas($ot);
        $this->completarChecklist($ot);
        app(EstadoOtService::class)->recalcular($ot->fresh());

        try {
            app(SalidaEquipoService::class)->solicitar($ot->fresh(['estado']), $this->jefeDeTaller());
            $this->fail('Debía bloquear la salida con herramientas sin devolver.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('herramientas', $e->getMessage());
        }

        app(OtHerramientaService::class)->devolver($asig->fresh(), $this->jefeDeTaller(), 'disponible');

        app(SalidaEquipoService::class)->solicitar($ot->fresh(['estado']), $this->jefeDeTaller());
        $this->assertSame('solicitada', $ot->fresh()->salida_estado);
    }

    public function test_las_herramientas_no_entran_al_costeo_de_repuestos(): void
    {
        $ot = $this->crearOt(tareas: 1, datos: ['valor_proyecto' => 1_000_000]);
        $tool = Inventario::factory()->herramienta()->create(['costo_unitario' => 900_000]);
        app(OtHerramientaService::class)->asignar($ot, $tool, $this->jefeDeTaller());

        $costeo = app(CosteoOtService::class)->calcular($ot->fresh());

        $this->assertEqualsWithDelta(0, $costeo['repuestos'], 0.01);
    }
}
