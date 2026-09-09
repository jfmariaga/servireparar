<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Events\OtEntregada;
use App\Services\OrdenTrabajo\EstadoOtService;
use App\Services\OrdenTrabajo\SalidaEquipoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EntregaEquipoTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    private function otFinalizada(): \App\Models\OrdenTrabajo
    {
        $ot = $this->crearOt(tareas: 1);
        $ot->update(['valor_proyecto' => 1_000_000]); // requerido para solicitar la salida (Phase 11)
        $this->finalizarTodasLasTareas($ot);
        $this->completarChecklist($ot);
        app(EstadoOtService::class)->recalcular($ot->fresh());

        return $ot->fresh(['estado']);
    }

    public function test_flujo_completo_solicitud_aprobacion_entrega(): void
    {
        Event::fake([OtEntregada::class]);
        $ot = $this->otFinalizada();
        $this->assertSame('finalizada', $ot->estado->slug);

        $salida = app(SalidaEquipoService::class);
        $salida->solicitar($ot, $this->jefeDeTaller());
        $this->assertSame('solicitada', $ot->fresh()->salida_estado);

        $salida->aprobar($ot->fresh(), $this->administrador());
        $this->assertSame('aprobada', $ot->fresh()->salida_estado);

        $salida->confirmarEntrega($ot->fresh(), $this->jefeDeTaller(), 'Firma cliente');
        $ot->refresh();

        $this->assertSame('entregada', $ot->estado->slug);
        $this->assertNotNull($ot->fecha_entrega);
        $this->assertSame('Firma cliente', $ot->firma_cliente_url);
        Event::assertDispatched(OtEntregada::class);
    }

    public function test_rechazo_de_salida_reabre_la_ot_a_en_curso_con_motivo_en_bitacora(): void
    {
        $ot = $this->otFinalizada();
        $salida = app(SalidaEquipoService::class);
        $salida->solicitar($ot, $this->jefeDeTaller());

        $salida->rechazar($ot->fresh(), $this->administrador(), 'Falta ajustar la tapa lateral');

        $ot->refresh();
        $this->assertSame('rechazada', $ot->salida_estado);
        $this->assertSame('en_curso', $ot->estado->slug);
        $this->assertSame('Falta ajustar la tapa lateral', $ot->salida_motivo_rechazo);
        $this->assertDatabaseHas('ot_eventos', [
            'ot_id' => $ot->id,
            'tipo' => 'salida_rechazada',
        ]);
    }

    public function test_solo_el_administrador_aprueba_la_salida(): void
    {
        $ot = $this->otFinalizada();
        app(SalidaEquipoService::class)->solicitar($ot, $this->jefeDeTaller());

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot->fresh()])
            ->call('aprobarSalida')
            ->assertForbidden();

        $this->assertSame('solicitada', $ot->fresh()->salida_estado);
    }

    public function test_no_se_puede_confirmar_entrega_sin_aprobacion(): void
    {
        $ot = $this->otFinalizada();

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('confirmarEntrega');

        $this->assertSame('finalizada', $ot->fresh()->estado->slug);
        $this->assertNotSame('entregada', $ot->fresh()->estado->slug);
    }

    public function test_no_se_puede_solicitar_salida_si_la_ot_no_esta_finalizada(): void
    {
        $ot = $this->crearOt();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(SalidaEquipoService::class)->solicitar($ot, $this->jefeDeTaller());
    }

    public function test_no_se_puede_solicitar_salida_sin_valor_de_proyecto(): void
    {
        $ot = $this->otFinalizada();
        $ot->update(['valor_proyecto' => null]);

        try {
            app(SalidaEquipoService::class)->solicitar($ot->fresh(['estado']), $this->jefeDeTaller());
            $this->fail('Debía exigir el valor del proyecto.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('valor del proyecto', $e->getMessage());
        }

        $this->assertSame('no_solicitada', $ot->fresh()->salida_estado);
    }

    public function test_ot_con_salida_aprobada_queda_congelada(): void
    {
        $ot = $this->otFinalizada();
        $salida = app(SalidaEquipoService::class);
        $salida->solicitar($ot, $this->jefeDeTaller());
        $salida->aprobar($ot->fresh(['estado']), $this->administrador());

        $ot = $ot->fresh(['estado']);
        $jefe = $this->jefeDeTaller();
        $admin = $this->administrador();

        $this->assertTrue($ot->estaBloqueada());
        $this->assertTrue($jefe->cannot('update', $ot), 'No se puede corregir una OT con salida aprobada');
        $this->assertTrue($admin->cannot('manageCosteo', $ot), 'No se puede editar el costeo de una OT con salida aprobada');

        // La entrega sí sigue disponible.
        $salida->confirmarEntrega($ot, $jefe, 'Firma');
        $this->assertSame('entregada', $ot->fresh()->estado->slug);
    }
}
