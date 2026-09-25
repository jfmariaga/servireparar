<?php

namespace Tests\Feature\Reportes;

use App\Enums\RolPrioridad;
use App\Models\EstadoOt;
use App\Models\OrdenTrabajo;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Auditoría / actividad reciente: lista `ot_eventos` de TODAS las OT en un
 * solo lugar (hoy solo se consulta por OT individual desde su detalle),
 * restringido a Administrador.
 */
class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function administrador(): User
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole(RolPrioridad::Administrador->value);

        return $admin;
    }

    public function test_administrador_ve_eventos_de_varias_ot_juntos(): void
    {
        $creador = $this->administrador();
        $otA = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create(['creado_por' => $creador->id]);
        $otB = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create(['creado_por' => $creador->id]);

        $otA->registrarEvento('correccion', 'Se reasignó el técnico.', $creador);
        $otB->registrarEvento('correccion', 'Se corrigió la descripción.', $creador);

        Volt::actingAs($creador)
            ->test('reportes.auditoria')
            ->assertSee($otA->numero_ot)
            ->assertSee('Se reasignó el técnico.')
            ->assertSee($otB->numero_ot)
            ->assertSee('Se corrigió la descripción.');
    }

    public function test_filtra_por_tipo_de_evento(): void
    {
        $creador = $this->administrador();
        $ot = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create();
        $ot->registrarEvento('correccion', 'Evento de corrección.', $creador);
        $ot->registrarEvento('evidencia', 'Se subió evidencia.', $creador);

        Volt::actingAs($creador)
            ->test('reportes.auditoria')
            ->set('tipo', 'evidencia')
            ->assertSee('Se subió evidencia.')
            ->assertDontSee('Evento de corrección.');
    }

    public function test_jefe_de_taller_no_puede_ver_la_auditoria(): void
    {
        $jefe = User::factory()->create(['estado' => 'activo']);
        $jefe->assignRole(RolPrioridad::JefeDeTaller->value);

        Volt::actingAs($jefe)
            ->test('reportes.auditoria')
            ->assertForbidden();
    }
}
