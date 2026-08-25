<?php

namespace Tests\Feature\Equipos;

use App\Enums\RolPrioridad;
use App\Models\Cliente;
use App\Models\Equipo;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrarEquipoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function jefeDeTaller(): User
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole(RolPrioridad::JefeDeTaller->value);

        return $user;
    }

    public function test_jefe_de_taller_registra_un_equipo_vinculado_a_cliente_existente(): void
    {
        $cliente = Cliente::factory()->create(['estado' => 'activo']);

        Volt::actingAs($this->jefeDeTaller())
            ->test('equipos.index')
            ->call('nuevo')
            ->set('clienteId', $cliente->id)
            ->set('tipo', 'Aire acondicionado')
            ->set('marca', 'LG')
            ->set('serie', 'SN-12345')
            ->call('guardar')
            ->assertSet('mostrarForm', false)
            ->assertHasNoErrors();

        $equipo = Equipo::where('serie', 'SN-12345')->first();
        $this->assertNotNull($equipo);
        $this->assertSame($cliente->id, $equipo->cliente_id);
        $this->assertSame('operativo', $equipo->estado);
    }

    public function test_tecnico_no_puede_gestionar_equipos(): void
    {
        $tecnico = User::factory()->create(['estado' => 'activo']);
        $tecnico->assignRole(RolPrioridad::Tecnico->value);

        Volt::actingAs($tecnico)->test('equipos.index')
            ->assertForbidden();
    }

    public function test_filtra_equipos_por_texto_de_busqueda(): void
    {
        $cliente = Cliente::factory()->create();
        Equipo::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'Compresor', 'serie' => 'AAA111']);
        Equipo::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'Motor eléctrico', 'serie' => 'ZZZ999']);

        Volt::actingAs($this->jefeDeTaller())
            ->test('equipos.index')
            ->set('busqueda', 'Compresor')
            ->assertSee('Compresor')
            ->assertDontSee('Motor eléctrico');
    }
}
