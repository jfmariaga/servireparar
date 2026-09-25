<?php

namespace Tests\Feature;

use App\Enums\RolPrioridad;
use App\Models\Cliente;
use App\Models\EstadoOt;
use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Buscador global (Cmd+K): salta a OT/cliente/inventario respetando la misma
 * visibilidad que cada listado ya tiene.
 */
class BuscadorGlobalTest extends TestCase
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

    public function test_no_busca_nada_hasta_abrir_y_escribir_al_menos_dos_letras(): void
    {
        OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create(['descripcion' => 'Reparación de compresor']);

        Volt::actingAs($this->administrador())
            ->test('buscador-global')
            ->set('q', 'Reparación')
            ->assertDontSee('Reparación de compresor')
            ->call('abrir')
            ->set('q', 'R')
            ->assertSee('al menos 2 letras')
            ->set('q', 'Re')
            ->assertSee('compresor');
    }

    public function test_encuentra_ot_cliente_e_inventario_por_termino_comun(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Transportes Andina']);
        OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->for($cliente, 'cliente')->create(['descripcion' => 'Mantenimiento Andina']);
        Inventario::factory()->create(['nombre' => 'Filtro Andina']);

        Volt::actingAs($this->administrador())
            ->test('buscador-global')
            ->call('abrir')
            ->set('q', 'Andina')
            ->assertSee('Transportes Andina')
            ->assertSee('Filtro Andina');
    }

    public function test_tecnico_no_ve_resultados_de_clientes_ni_inventario(): void
    {
        Cliente::factory()->create(['nombre' => 'Cliente Confidencial']);
        Inventario::factory()->create(['nombre' => 'Repuesto Confidencial']);

        $tecnicoUser = User::factory()->create(['estado' => 'activo']);
        $tecnicoUser->assignRole(RolPrioridad::Tecnico->value);

        Volt::actingAs($tecnicoUser)
            ->test('buscador-global')
            ->call('abrir')
            ->set('q', 'Confidencial')
            ->assertDontSee('Cliente Confidencial')
            ->assertDontSee('Repuesto Confidencial');
    }

    public function test_cerrar_limpia_la_busqueda(): void
    {
        OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create(['descripcion' => 'Cambio de rodamiento']);

        Volt::actingAs($this->administrador())
            ->test('buscador-global')
            ->call('abrir')
            ->set('q', 'rodamiento')
            ->assertSee('rodamiento')
            ->call('cerrar')
            ->assertSet('q', '')
            ->assertSet('abierto', false);
    }
}
