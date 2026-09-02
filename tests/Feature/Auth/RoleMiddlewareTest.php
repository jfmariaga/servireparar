<?php

namespace Tests\Feature\Auth;

use App\Enums\RolPrioridad;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
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

    public function test_vendedor_accede_a_su_dashboard_pero_no_al_de_administrador(): void
    {
        $vendedor = User::factory()->create(['estado' => 'activo']);
        $vendedor->assignRole(RolPrioridad::Vendedor->value);

        $this->actingAs($vendedor)
            ->get(route('dashboard.vendedor'))
            ->assertOk();

        $this->actingAs($vendedor)
            ->get(route('dashboard.administrador'))
            ->assertForbidden();
    }

    public function test_redireccion_post_login_prioriza_almacenista_sobre_vendedor(): void
    {
        $user = User::factory()->create([
            'email' => 'almacen-vendedor@servireparar.com',
            'password' => 'password123',
            'estado' => 'activo',
        ]);
        $user->assignRole([RolPrioridad::Vendedor->value, RolPrioridad::Almacenista->value]);

        Volt::test('auth.login')
            ->set('email', 'almacen-vendedor@servireparar.com')
            ->set('password', 'password123')
            ->call('login')
            ->assertRedirect(route('dashboard.almacenista'));
    }
}
