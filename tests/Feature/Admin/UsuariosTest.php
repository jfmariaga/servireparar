<?php

namespace Tests\Feature\Admin;

use App\Enums\RolPrioridad;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function administrador(): User
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole(RolPrioridad::Administrador->value);

        return $user;
    }

    public function test_administrador_puede_crear_usuario_con_rol(): void
    {
        Volt::actingAs($this->administrador())
            ->test('admin.usuarios.index')
            ->call('nuevo')
            ->set('name', 'Nuevo Técnico')
            ->set('email', 'tecnico2@servireparar.com')
            ->set('password', 'password123')
            ->set('roles', [RolPrioridad::Tecnico->value])
            ->call('guardar')
            ->assertSet('mostrarForm', false);

        $creado = User::where('email', 'tecnico2@servireparar.com')->first();
        $this->assertNotNull($creado);
        $this->assertTrue($creado->hasRole(RolPrioridad::Tecnico->value));
    }

    public function test_no_permite_inactivar_al_ultimo_administrador_activo(): void
    {
        $unicoAdmin = $this->administrador();

        Volt::actingAs($unicoAdmin)
            ->test('admin.usuarios.index')
            ->call('alternarEstado', $unicoAdmin->id)
            ->assertSet('errorDesactivar', 'No puedes inactivar al último Administrador activo del sistema.');

        $this->assertEquals('activo', $unicoAdmin->fresh()->estado);
    }

    public function test_permite_inactivar_administrador_si_hay_otro_activo(): void
    {
        $adminQueGestiona = $this->administrador();
        $otroAdmin = User::factory()->create(['estado' => 'activo']);
        $otroAdmin->assignRole(RolPrioridad::Administrador->value);

        Volt::actingAs($adminQueGestiona)
            ->test('admin.usuarios.index')
            ->call('alternarEstado', $otroAdmin->id)
            ->assertSet('errorDesactivar', '');

        $this->assertEquals('inactivo', $otroAdmin->fresh()->estado);
    }

    public function test_tecnico_no_puede_acceder_a_gestion_de_usuarios(): void
    {
        $tecnico = User::factory()->create(['estado' => 'activo']);
        $tecnico->assignRole(RolPrioridad::Tecnico->value);

        Volt::actingAs($tecnico)
            ->test('admin.usuarios.index')
            ->assertForbidden();
    }
}
