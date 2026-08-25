<?php

namespace Tests\Feature\Auth;

use App\Enums\RolPrioridad;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifica el middleware de rol reutilizable (spec 001, T030) aplicado a las
 * rutas de dashboard por rol (FR-006: impedir acceso a pantallas no autorizadas).
 */
class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_tecnico_no_puede_acceder_al_dashboard_de_administrador(): void
    {
        $tecnico = User::factory()->create(['estado' => 'activo']);
        $tecnico->assignRole(RolPrioridad::Tecnico->value);

        $this->actingAs($tecnico)
            ->get(route('dashboard.administrador'))
            ->assertForbidden();
    }

    public function test_administrador_puede_acceder_a_su_propio_dashboard(): void
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole(RolPrioridad::Administrador->value);

        $this->actingAs($admin)
            ->get(route('dashboard.administrador'))
            ->assertOk();
    }
}
