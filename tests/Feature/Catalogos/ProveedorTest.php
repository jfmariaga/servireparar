<?php

namespace Tests\Feature\Catalogos;

use App\Enums\RolPrioridad;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProveedorTest extends TestCase
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

    public function test_administrador_puede_crear_un_proveedor(): void
    {
        Volt::actingAs($this->administrador())
            ->test('proveedores.index')
            ->call('nuevo')
            ->set('nombre', 'Almacén Central')
            ->set('nit', '800111333-1')
            ->call('guardar')
            ->assertSet('mostrarForm', false);

        $this->assertDatabaseHas('proveedores', ['nombre' => 'Almacén Central']);
    }

    public function test_advierte_pero_no_bloquea_correo_duplicado(): void
    {
        Proveedor::factory()->create(['correo' => 'ventas@proveedor.com']);

        Volt::actingAs($this->administrador())
            ->test('proveedores.index')
            ->call('nuevo')
            ->set('nombre', 'Otro Proveedor')
            ->set('correo', 'ventas@proveedor.com')
            ->call('verificarDuplicado')
            ->assertSet('advertenciaDuplicado', 'Ya existe un proveedor registrado con el correo ventas@proveedor.com.')
            ->call('guardar');

        $this->assertDatabaseCount('proveedores', 2);
    }

    public function test_proveedor_inactivo_no_aparece_en_listado_de_activos(): void
    {
        Proveedor::factory()->create(['nombre' => 'Proveedor Activo', 'estado' => 'activo']);
        Proveedor::factory()->create(['nombre' => 'Proveedor Inactivo', 'estado' => 'inactivo']);

        Volt::actingAs($this->administrador())
            ->test('proveedores.index')
            ->assertSee('Proveedor Activo')
            ->assertDontSee('Proveedor Inactivo');
    }
}
