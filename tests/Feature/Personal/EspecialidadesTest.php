<?php

namespace Tests\Feature\Personal;

use App\Enums\RolPrioridad;
use App\Models\Especialidad;
use App\Models\Tecnico;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EspecialidadesTest extends TestCase
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

    public function test_administrador_crea_una_especialidad(): void
    {
        Volt::actingAs($this->administrador())
            ->test('personal.especialidades')
            ->call('nueva')
            ->set('nombre', 'Hidráulica')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('mostrarForm', false);

        $this->assertNotNull(Especialidad::where('nombre', 'Hidráulica')->first());
    }

    public function test_no_permite_nombres_duplicados(): void
    {
        Especialidad::factory()->create(['nombre' => 'Eléctrico']);

        Volt::actingAs($this->administrador())
            ->test('personal.especialidades')
            ->call('nueva')
            ->set('nombre', 'Eléctrico')
            ->call('guardar')
            ->assertHasErrors(['nombre']);
    }

    public function test_inactivar_no_afecta_a_tecnicos_ya_asignados(): void
    {
        $especialidad = Especialidad::factory()->create(['activo' => true]);
        Tecnico::factory()->create(['especialidad_id' => $especialidad->id]);

        Volt::actingAs($this->administrador())
            ->test('personal.especialidades')
            ->call('alternar', $especialidad->id);

        $this->assertFalse($especialidad->fresh()->activo);
        $this->assertSame($especialidad->id, Tecnico::first()->especialidad_id);
    }

    public function test_especialidad_inactiva_no_aparece_en_el_selector_de_usuarios(): void
    {
        Especialidad::factory()->create(['nombre' => 'Activa Uno', 'activo' => true]);
        Especialidad::factory()->create(['nombre' => 'Inactiva Uno', 'activo' => false]);

        $component = Volt::actingAs($this->administrador())
            ->test('admin.usuarios.index')
            ->call('nuevo')
            ->set('roles', [RolPrioridad::Tecnico->value]);

        $component->assertSee('Activa Uno');
        $component->assertDontSee('Inactiva Uno');
    }

    public function test_tecnico_no_puede_gestionar_especialidades(): void
    {
        $tecnico = User::factory()->create(['estado' => 'activo']);
        $tecnico->assignRole(RolPrioridad::Tecnico->value);

        Volt::actingAs($tecnico)->test('personal.especialidades')->assertForbidden();
    }
}
