<?php

namespace Tests\Feature\Compras;

use App\Enums\RolPrioridad;
use App\Models\Compra;
use App\Models\Inventario;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Smoke test de las pantallas de Compras (spec 006, US4).
 */
class ComprasUiTest extends TestCase
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

    public function test_tablero_lista_compras(): void
    {
        $admin = $this->administrador();
        Compra::factory()->create(['numero' => 'COM-0001']);

        Volt::actingAs($admin)->test('compras.tablero')->assertSee('COM-0001');
    }

    public function test_form_crea_una_compra(): void
    {
        $admin = $this->administrador();
        $proveedor = Proveedor::factory()->create();
        $item = Inventario::factory()->create();

        Volt::actingAs($admin)->test('compras.form')
            ->set('proveedorId', $proveedor->id)
            ->set('items.0.inventario_id', $item->id)
            ->set('items.0.cantidad', 5)
            ->set('items.0.costo_unitario', 12000)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('compras', ['proveedor_id' => $proveedor->id]);
    }

    public function test_gestionar_avanza_estados_desde_la_ui(): void
    {
        $admin = $this->administrador();
        $compra = Compra::factory()->create(['estado' => 'recepcion']);

        Volt::actingAs($admin)->test('compras.gestionar', ['compra' => $compra])
            ->call('marcarCotizacion')
            ->assertHasNoErrors();

        $this->assertSame('cotizacion', $compra->fresh()->estado);
    }
}
