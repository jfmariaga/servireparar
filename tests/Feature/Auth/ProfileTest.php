<?php

namespace Tests\Feature\Auth;

use App\Enums\RolPrioridad;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_usuario_ve_sus_propios_datos_y_roles(): void
    {
        $user = User::factory()->create(['name' => 'Pepito Pérez', 'estado' => 'activo']);
        $user->assignRole(RolPrioridad::Tecnico->value);

        Volt::actingAs($user)
            ->test('auth.profile')
            ->assertSee('Pepito Pérez')
            ->assertSee($user->email)
            ->assertSee(RolPrioridad::Tecnico->value);
    }

    public function test_usuario_edita_nombre_y_telefono(): void
    {
        $user = User::factory()->create(['estado' => 'activo']);

        Volt::actingAs($user)
            ->test('auth.profile')
            ->set('name', 'Nombre Actualizado')
            ->set('telefono', '3001234567')
            ->call('guardarDatos');

        $this->assertSame('Nombre Actualizado', $user->fresh()->name);
        $this->assertSame('3001234567', $user->fresh()->telefono);
    }

    public function test_no_puede_cambiar_su_propio_rol_desde_el_perfil(): void
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole(RolPrioridad::Tecnico->value);

        Volt::actingAs($user)->test('auth.profile');

        // El componente de perfil no expone ninguna propiedad ni acción de roles.
        $this->assertTrue($user->fresh()->hasRole(RolPrioridad::Tecnico->value));
        $this->assertCount(1, $user->fresh()->roles);
    }

    public function test_cambia_contrasena_requiriendo_la_actual(): void
    {
        $user = User::factory()->create(['password' => 'password-actual', 'estado' => 'activo']);

        Volt::actingAs($user)
            ->test('auth.profile')
            ->set('password_actual', 'password-incorrecta')
            ->set('password_nueva', 'password-nueva-123')
            ->set('password_nueva_confirmation', 'password-nueva-123')
            ->call('cambiarContrasena')
            ->assertHasErrors('password_actual');

        $this->assertTrue(Hash::check('password-actual', $user->fresh()->password));

        Volt::actingAs($user)
            ->test('auth.profile')
            ->set('password_actual', 'password-actual')
            ->set('password_nueva', 'password-nueva-123')
            ->set('password_nueva_confirmation', 'password-nueva-123')
            ->call('cambiarContrasena')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('password-nueva-123', $user->fresh()->password));
    }
}
