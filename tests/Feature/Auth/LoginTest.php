<?php

namespace Tests\Feature\Auth;

use App\Enums\RolPrioridad;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_usuario_activo_con_credenciales_correctas_inicia_sesion(): void
    {
        $user = User::factory()->create([
            'email' => 'tecnico@servireparar.com',
            'password' => 'password123',
            'estado' => 'activo',
        ]);
        $user->assignRole(RolPrioridad::Tecnico->value);

        Volt::test('auth.login')
            ->set('email', 'tecnico@servireparar.com')
            ->set('password', 'password123')
            ->call('login')
            ->assertRedirect(route(RolPrioridad::rutaDashboard(RolPrioridad::Tecnico->value)));

        $this->assertAuthenticatedAs($user);
    }

    public function test_credenciales_invalidas_muestran_error_generico(): void
    {
        User::factory()->create([
            'email' => 'existe@servireparar.com',
            'password' => 'password123',
            'estado' => 'activo',
        ]);

        Volt::test('auth.login')
            ->set('email', 'existe@servireparar.com')
            ->set('password', 'incorrecta')
            ->call('login')
            ->assertSet('errorMessage', 'Las credenciales no coinciden con nuestros registros.');

        $this->assertGuest();
    }

    public function test_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        User::factory()->create([
            'email' => 'inactivo@servireparar.com',
            'password' => 'password123',
            'estado' => 'inactivo',
        ]);

        Volt::test('auth.login')
            ->set('email', 'inactivo@servireparar.com')
            ->set('password', 'password123')
            ->call('login')
            ->assertSet('errorMessage', 'Tu cuenta está inactiva. Contacta al Administrador.');

        $this->assertGuest();
    }

    public function test_redirige_al_dashboard_de_mayor_prioridad_cuando_hay_multiples_roles(): void
    {
        $user = User::factory()->create([
            'email' => 'multirol@servireparar.com',
            'password' => 'password123',
            'estado' => 'activo',
        ]);
        $user->assignRole([RolPrioridad::Tecnico->value, RolPrioridad::JefeDeTaller->value]);

        Volt::test('auth.login')
            ->set('email', 'multirol@servireparar.com')
            ->set('password', 'password123')
            ->call('login')
            ->assertRedirect(route(RolPrioridad::rutaDashboard(RolPrioridad::JefeDeTaller->value)));
    }
}
