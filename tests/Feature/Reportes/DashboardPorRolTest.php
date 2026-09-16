<?php

namespace Tests\Feature\Reportes;

use App\Enums\RolPrioridad;
use App\Models\DetalleOt;
use App\Models\EstadoOt;
use App\Models\OrdenTrabajo;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 007, US1 (FR-001): dashboard por rol al ingresar, con indicadores
 * reales calculados por consulta directa.
 */
class DashboardPorRolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_jefe_de_taller_ve_indicadores_de_operacion_y_trabajo_en_ejecucion(): void
    {
        $jefe = User::factory()->create(['estado' => 'activo']);
        $jefe->assignRole(RolPrioridad::JefeDeTaller->value);

        $ot = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create();
        DetalleOt::factory()->for($ot, 'ordenTrabajo')->enCurso()->create(['descripcion' => 'Cambio de compresor']);

        Volt::actingAs($jefe)
            ->test('dashboard')
            ->assertSee('Indicadores de operación')
            ->assertSee('OT abiertas')
            ->assertSee('Trabajo en ejecución')
            ->assertSee('Cambio de compresor');
    }

    public function test_administrador_ve_los_mismos_indicadores(): void
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole(RolPrioridad::Administrador->value);

        Volt::actingAs($admin)
            ->test('dashboard')
            ->assertSee('Indicadores de operación')
            ->assertSee('Productividad del equipo');
    }

    public function test_vendedor_sigue_viendo_el_mensaje_de_placeholder(): void
    {
        $vendedor = User::factory()->create(['estado' => 'activo']);
        $vendedor->assignRole(RolPrioridad::Vendedor->value);

        Volt::actingAs($vendedor)
            ->test('dashboard')
            ->assertDontSee('Indicadores de operación')
            ->assertSee('menú de navegación');
    }
}
