<?php

namespace Tests\Feature\Inventario;

use App\Enums\RolPrioridad;
use App\Models\CategoriaInventario;
use App\Models\Inventario;
use App\Models\UnidadMedida;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CatalogosMaestrosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function almacenista(): User
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole(RolPrioridad::Almacenista->value);

        return $user;
    }

    public function test_crea_una_categoria_con_prefijo_fijo(): void
    {
        Volt::actingAs($this->almacenista())
            ->test('inventario.catalogos')
            ->call('nuevaCategoria')
            ->set('categoriaNombre', 'Filtros')
            ->set('categoriaPrefijo', 'CON-')
            ->call('guardarCategoria')
            ->assertHasNoErrors();

        $categoria = CategoriaInventario::where('nombre', 'Filtros')->firstOrFail();
        $this->assertSame('CON-', $categoria->prefijo_codigo);
    }

    public function test_no_permite_categorias_con_nombre_duplicado(): void
    {
        CategoriaInventario::factory()->create(['nombre' => 'Repuestos']);

        Volt::actingAs($this->almacenista())
            ->test('inventario.catalogos')
            ->call('nuevaCategoria')
            ->set('categoriaNombre', 'Repuestos')
            ->call('guardarCategoria')
            ->assertHasErrors(['categoriaNombre']);
    }

    public function test_inactivar_categoria_no_afecta_items_existentes(): void
    {
        $categoria = CategoriaInventario::factory()->create(['activo' => true]);
        Inventario::factory()->create(['categoria_id' => $categoria->id]);

        Volt::actingAs($this->almacenista())
            ->test('inventario.catalogos')
            ->call('alternarCategoria', $categoria->id);

        $this->assertFalse($categoria->fresh()->activo);
        $this->assertSame($categoria->id, Inventario::first()->categoria_id);
    }

    public function test_categoria_inactiva_no_aparece_en_selector_de_nuevo_item(): void
    {
        CategoriaInventario::factory()->create(['nombre' => 'Categoría Activa', 'activo' => true]);
        CategoriaInventario::factory()->create(['nombre' => 'Categoría Inactiva', 'activo' => false]);

        $component = Volt::actingAs($this->almacenista())->test('inventario.catalogo')->call('nuevo');

        $component->assertSee('Categoría Activa');
        $component->assertDontSee('Categoría Inactiva');
    }

    public function test_crea_una_unidad_de_medida(): void
    {
        Volt::actingAs($this->almacenista())
            ->test('inventario.catalogos')
            ->call('nuevaUnidad')
            ->set('unidadNombre', 'Metro cuadrado')
            ->set('unidadAbreviatura', 'm2')
            ->call('guardarUnidad')
            ->assertHasNoErrors();

        $this->assertNotNull(UnidadMedida::where('nombre', 'Metro cuadrado')->first());
    }

    public function test_no_permite_unidades_con_nombre_duplicado(): void
    {
        UnidadMedida::factory()->create(['nombre' => 'Galón']);

        Volt::actingAs($this->almacenista())
            ->test('inventario.catalogos')
            ->call('nuevaUnidad')
            ->set('unidadNombre', 'Galón')
            ->call('guardarUnidad')
            ->assertHasErrors(['unidadNombre']);
    }

    public function test_item_de_inventario_usa_unidad_del_catalogo(): void
    {
        $categoria = CategoriaInventario::factory()->create();
        $unidad = UnidadMedida::factory()->create(['nombre' => 'Kilogramo', 'abreviatura' => 'kg']);

        Volt::actingAs($this->almacenista())
            ->test('inventario.catalogo')
            ->call('nuevo')
            ->set('categoriaId', $categoria->id)
            ->set('nombre', 'Pintura anticorrosiva')
            ->set('unidadMedidaId', $unidad->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $item = Inventario::where('nombre', 'Pintura anticorrosiva')->firstOrFail();
        $this->assertSame($unidad->id, $item->unidad_medida_id);
    }
}
