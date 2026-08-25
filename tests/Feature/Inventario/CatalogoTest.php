<?php

namespace Tests\Feature\Inventario;

use App\Enums\RolPrioridad;
use App\Models\CategoriaInventario;
use App\Models\Inventario;
use App\Models\User;
use Database\Seeders\CategoriasInventarioSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CatalogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(CategoriasInventarioSeeder::class);
    }

    private function almacenista(): User
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole(RolPrioridad::Almacenista->value);

        return $user;
    }

    public function test_registra_item_con_codigo_autogenerado_y_ubicacion_valida(): void
    {
        $categoria = CategoriaInventario::where('nombre', 'Repuestos')->firstOrFail();

        Volt::actingAs($this->almacenista())
            ->test('inventario.catalogo')
            ->call('nuevo')
            ->set('categoriaId', $categoria->id)
            ->set('nombre', 'Rodamiento 6205')
            ->set('tipo', 'consumible')
            ->set('ubicacion', 'A-01-01')
            ->set('stockMinimo', '5')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('mostrarForm', false);

        $item = Inventario::where('nombre', 'Rodamiento 6205')->firstOrFail();
        $this->assertSame('REP-00001', $item->codigo);
        $this->assertSame($item->codigo, $item->codigo_barras);
        $this->assertSame('A-01-01', $item->ubicacion);
    }

    public function test_rechaza_formato_de_ubicacion_invalido(): void
    {
        $categoria = CategoriaInventario::first();

        Volt::actingAs($this->almacenista())
            ->test('inventario.catalogo')
            ->call('nuevo')
            ->set('categoriaId', $categoria->id)
            ->set('nombre', 'Ítem con ubicación mala')
            ->set('ubicacion', 'ubicacion-invalida')
            ->call('guardar')
            ->assertHasErrors(['ubicacion']);
    }

    public function test_advierte_sin_bloquear_colision_de_ubicacion(): void
    {
        $categoria = CategoriaInventario::first();
        Inventario::factory()->create(['categoria_id' => $categoria->id, 'ubicacion' => 'B-02-02']);

        $component = Volt::actingAs($this->almacenista())
            ->test('inventario.catalogo')
            ->call('nuevo')
            ->set('categoriaId', $categoria->id)
            ->set('nombre', 'Otro ítem')
            ->set('ubicacion', 'B-02-02')
            ->set('ubicacion', 'B-02-02') // dispara wire:blur equivalente
            ->call('verificarUbicacion');

        $component->assertSet('advertenciaUbicacion', 'Ya hay otro ítem registrado en la ubicación B-02-02.');

        // La advertencia no bloquea el guardado.
        $component->call('guardar')->assertHasNoErrors();
    }
}
