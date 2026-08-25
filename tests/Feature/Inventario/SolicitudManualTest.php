<?php

namespace Tests\Feature\Inventario;

use App\Enums\RolPrioridad;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SolicitudManualTest extends TestCase
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

    public function test_registra_solicitud_manual_sin_ot_asociada(): void
    {
        $cliente = Cliente::factory()->create(['estado' => 'activo']);
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 50]);

        Volt::actingAs($this->almacenista())
            ->test('inventario.movimientos')
            ->call('nuevaSolicitud')
            ->set('clienteId', $cliente->id)
            ->set('inventarioId', $item->id)
            ->set('cantidad', '10')
            ->call('registrarSolicitud')
            ->assertSet('mostrarForm', false)
            ->assertHasNoErrors();

        $movimiento = MovimientoInventario::where('inventario_id', $item->id)->first();
        $this->assertNotNull($movimiento);
        $this->assertSame('manual', $movimiento->origen);
        $this->assertSame($cliente->id, $movimiento->cliente_id);
        $this->assertEquals(40, $item->fresh()->stock_actual);
    }

    public function test_solicitud_manual_queda_distinguible_de_movimientos_por_ot(): void
    {
        $cliente = Cliente::factory()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 50]);

        Volt::actingAs($this->almacenista())
            ->test('inventario.movimientos')
            ->set('clienteId', $cliente->id)
            ->set('inventarioId', $item->id)
            ->set('cantidad', '5')
            ->call('registrarSolicitud');

        $this->assertSame(1, MovimientoInventario::where('origen', 'manual')->count());
        $this->assertSame(0, MovimientoInventario::where('origen', 'ot')->count());
    }
}
