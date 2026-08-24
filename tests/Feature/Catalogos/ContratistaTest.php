<?php

namespace Tests\Feature\Catalogos;

use App\Enums\RolPrioridad;
use App\Models\Contratista;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ContratistaTest extends TestCase
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

    public function test_administrador_puede_crear_un_contratista_con_especialidad_libre(): void
    {
        Volt::actingAs($this->administrador())
            ->test('contratistas.index')
            ->call('nuevo')
            ->set('nombre', 'Soluciones Ballestas')
            ->set('especialidad', 'Calcomanías')
            ->call('guardar')
            ->assertSet('mostrarForm', false);

        $this->assertDatabaseHas('contratistas', [
            'nombre' => 'Soluciones Ballestas',
            'especialidad' => 'Calcomanías',
        ]);
    }

    public function test_contratista_es_entidad_independiente_de_proveedor(): void
    {
        // No debe existir ninguna FK/relación directa entre contratistas y proveedores.
        $columnas = Schema::getColumnListing('contratistas');
        $this->assertNotContains('proveedor_id', $columnas);
    }

    public function test_contratista_inactivo_no_aparece_en_listado_de_activos(): void
    {
        Contratista::factory()->create(['nombre' => 'Contratista Activo', 'estado' => 'activo']);
        Contratista::factory()->create(['nombre' => 'Contratista Inactivo', 'estado' => 'inactivo']);

        Volt::actingAs($this->administrador())
            ->test('contratistas.index')
            ->assertSee('Contratista Activo')
            ->assertDontSee('Contratista Inactivo');
    }
}
