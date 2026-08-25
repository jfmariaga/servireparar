<?php

namespace Tests\Feature\Inventario;

use App\Enums\RolPrioridad;
use App\Models\CategoriaInventario;
use App\Models\Inventario;
use App\Models\User;
use App\Services\Inventario\MovimientoService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    public function test_calcula_el_valor_total_real_del_inventario(): void
    {
        $usuario = User::factory()->create();
        $servicio = new MovimientoService();

        $itemA = Inventario::factory()->create(['stock_actual' => 0]);
        $servicio->entrada($itemA, 10, $usuario, costoUnitario: 1000);

        $itemB = Inventario::factory()->create(['stock_actual' => 0]);
        $servicio->entrada($itemB, 5, $usuario, costoUnitario: 2000);

        $component = Volt::actingAs($this->almacenista())->test('inventario.dashboard');

        // 10*1000 + 5*2000 = 20000 — la suma real de los lotes, no un promedio
        $this->assertEquals(20000, $component->viewData('valorTotal'));
        $this->assertEquals(2, $component->viewData('totalItems'));
    }

    public function test_cuenta_items_bajo_stock_minimo(): void
    {
        Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 2, 'stock_minimo' => 10]);
        Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 50, 'stock_minimo' => 10]);

        $component = Volt::actingAs($this->almacenista())->test('inventario.dashboard');

        $this->assertEquals(1, $component->viewData('itemsBajoMinimo'));
    }

    public function test_agrupa_valor_por_categoria(): void
    {
        $usuario = User::factory()->create();
        $categoria = CategoriaInventario::factory()->create();
        $item = Inventario::factory()->create(['categoria_id' => $categoria->id, 'stock_actual' => 0]);
        (new MovimientoService())->entrada($item, 4, $usuario, costoUnitario: 500);

        $component = Volt::actingAs($this->almacenista())->test('inventario.dashboard');

        $fila = collect($component->viewData('porCategoria'))->first(fn ($fila) => $fila['categoria']->id === $categoria->id);
        $this->assertNotNull($fila);
        $this->assertEquals(2000, $fila['valor']);
    }
}
