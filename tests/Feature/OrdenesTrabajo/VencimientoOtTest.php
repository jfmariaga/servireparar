<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Events\OtProximaAVencer;
use App\Services\OrdenTrabajo\VencimientoOtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class VencimientoOtTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
        config(['ot.dias_umbral_vencimiento' => 2]);
    }

    public function test_dispara_alerta_para_ot_dentro_del_umbral(): void
    {
        Event::fake([OtProximaAVencer::class]);

        // Estimada en 5 días, creada hace 4 → faltan ~1 día ≤ umbral(2).
        $ot = $this->crearOt(['tiempo_estimado_dias' => 5], tareas: 1);
        $ot->forceFill(['created_at' => now()->subDays(4)])->save();

        $disparadas = app(VencimientoOtService::class)->revisar();

        $this->assertSame(1, $disparadas);
        Event::assertDispatched(OtProximaAVencer::class, fn ($e) => $e->ordenTrabajo->is($ot) && $e->vencida === false);
    }

    public function test_marca_como_vencida_cuando_ya_paso_el_limite(): void
    {
        Event::fake([OtProximaAVencer::class]);

        $ot = $this->crearOt(['tiempo_estimado_dias' => 3], tareas: 1);
        $ot->forceFill(['created_at' => now()->subDays(10)])->save();

        app(VencimientoOtService::class)->revisar();

        Event::assertDispatched(OtProximaAVencer::class, fn ($e) => $e->ordenTrabajo->is($ot) && $e->vencida === true);
    }

    public function test_no_alerta_ot_holgada_ni_finalizada(): void
    {
        Event::fake([OtProximaAVencer::class]);

        $holgada = $this->crearOt(['tiempo_estimado_dias' => 30], tareas: 1);
        $holgada->forceFill(['created_at' => now()->subDay()])->save();

        $finalizada = $this->crearOt(['tiempo_estimado_dias' => 1], tareas: 1);
        $finalizada->forceFill(['created_at' => now()->subDays(10), 'estado_id' => \App\Models\EstadoOt::idPorSlug('finalizada')])->save();

        $disparadas = app(VencimientoOtService::class)->revisar();

        $this->assertSame(0, $disparadas);
        Event::assertNotDispatched(OtProximaAVencer::class);
    }
}
