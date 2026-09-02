<?php

namespace Tests\Feature\Personal;

use App\Models\SueldoTecnico;
use App\Models\Tecnico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sueldo historizado y valor día (spec 004, FR-009 a FR-011).
 */
class SueldoTecnicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_valor_dia_es_sueldo_entre_dias_mes(): void
    {
        config(['personal.dias_mes' => 30]);
        $tecnico = Tecnico::factory()->conSueldo(2_400_000)->create();

        $this->assertEquals(2_400_000, $tecnico->sueldoVigente());
        $this->assertEquals(80_000, $tecnico->valorDia());
    }

    public function test_divisor_configurable(): void
    {
        config(['personal.dias_mes' => 24]);
        $tecnico = Tecnico::factory()->conSueldo(2_400_000)->create();

        $this->assertEquals(100_000, $tecnico->valorDia());
    }

    public function test_subir_el_sueldo_crea_una_fila_y_no_altera_el_vigente_anterior(): void
    {
        $tecnico = Tecnico::factory()->create();
        $tecnico->registrarSueldo(2_000_000, Carbon::parse('2026-01-01'));
        $tecnico->registrarSueldo(2_600_000, Carbon::parse('2026-06-01'));

        $this->assertSame(2, SueldoTecnico::where('tecnico_id', $tecnico->id)->count());

        // A una fecha anterior al aumento, sigue el sueldo viejo.
        $this->assertEquals(2_000_000, $tecnico->sueldoVigente(Carbon::parse('2026-03-15')));
        $this->assertEquals(round(2_000_000 / 30, 2), $tecnico->valorDia(Carbon::parse('2026-03-15')));

        // Desde el aumento, el nuevo.
        $this->assertEquals(2_600_000, $tecnico->sueldoVigente(Carbon::parse('2026-07-01')));

        // Antes de cualquier registro, null.
        $this->assertNull($tecnico->sueldoVigente(Carbon::parse('2025-12-31')));
    }

    public function test_registrar_el_mismo_sueldo_no_duplica_la_fila(): void
    {
        $tecnico = Tecnico::factory()->create();
        $tecnico->registrarSueldo(2_000_000, Carbon::parse('2026-01-01'));
        $tecnico->registrarSueldo(2_000_000, Carbon::parse('2026-02-01'));

        $this->assertSame(1, SueldoTecnico::where('tecnico_id', $tecnico->id)->count());
    }
}
