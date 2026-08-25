<?php

namespace Tests\Feature\Equipos;

use App\Events\MantenimientoPreventivoProximoAVencer;
use App\Models\Equipo;
use App\Models\MantenimientoPreventivo;
use App\Services\Equipos\MantenimientoPreventivoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class MantenimientoPreventivoTest extends TestCase
{
    use RefreshDatabase;

    public function test_calcula_proxima_fecha_como_ultima_mas_periodicidad(): void
    {
        $equipo = Equipo::factory()->create(['periodicidad_mantenimiento_dias' => 90]);
        $fecha = now()->startOfDay();

        $mantenimiento = (new MantenimientoPreventivoService())
            ->registrarMantenimientoCompletado($equipo, $fecha);

        $this->assertTrue($fecha->toDateString() === $mantenimiento->ultima_fecha->toDateString());
        $this->assertTrue($fecha->copy()->addDays(90)->toDateString() === $mantenimiento->proxima_fecha->toDateString());
        $this->assertFalse($mantenimiento->alerta_disparada);
    }

    public function test_no_permite_registrar_mantenimiento_sin_periodicidad_configurada(): void
    {
        $equipo = Equipo::factory()->create(['periodicidad_mantenimiento_dias' => null]);

        $this->expectException(RuntimeException::class);

        (new MantenimientoPreventivoService())->registrarMantenimientoCompletado($equipo);
    }

    public function test_dispara_evento_al_revisar_vencimientos_proximos(): void
    {
        Event::fake();

        $proximo = MantenimientoPreventivo::factory()->create([
            'proxima_fecha' => now()->addDays(3),
            'alerta_disparada' => false,
        ]);

        MantenimientoPreventivo::factory()->create([
            'proxima_fecha' => now()->addDays(30),
            'alerta_disparada' => false,
        ]);

        $disparadas = (new MantenimientoPreventivoService())->revisarVencimientos(diasAntelacion: 7);

        $this->assertSame(1, $disparadas);
        Event::assertDispatched(MantenimientoPreventivoProximoAVencer::class, fn ($event) => $event->mantenimiento->id === $proximo->id
        );
        $this->assertTrue($proximo->fresh()->alerta_disparada);
    }

    public function test_no_repite_la_alerta_ya_disparada(): void
    {
        Event::fake();

        MantenimientoPreventivo::factory()->create([
            'proxima_fecha' => now()->addDays(1),
            'alerta_disparada' => true,
        ]);

        $disparadas = (new MantenimientoPreventivoService())->revisarVencimientos();

        $this->assertSame(0, $disparadas);
        Event::assertNotDispatched(MantenimientoPreventivoProximoAVencer::class);
    }
}
