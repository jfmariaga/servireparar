<?php

namespace Tests\Feature\Despacho;

use App\Enums\RolPrioridad;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\SolicitudDespacho;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CrearSolicitudDespachoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function vendedor(): User
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole(RolPrioridad::Vendedor->value);

        return $user;
    }

    public function test_vendedor_crea_solicitud_con_linea_de_inventario_y_linea_de_compra_externa(): void
    {
        $cliente = Cliente::factory()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 30]);

        Volt::actingAs($this->vendedor())
            ->test('despacho.form')
            ->set('clienteId', $cliente->id)
            ->set('sede', 'MDE')
            ->set('lineas', [
                ['origen' => 'inventario', 'inventario_id' => $item->id, 'descripcion' => '', 'cantidad' => '5', 'proveedor_externo' => '', 'costo_compra_externa' => ''],
                ['origen' => 'compra_externa', 'inventario_id' => null, 'descripcion' => 'Manguera especial', 'cantidad' => '2', 'proveedor_externo' => 'Ferretería X', 'costo_compra_externa' => '18000'],
            ])
            ->call('enviar')
            ->assertHasNoErrors();

        $solicitud = SolicitudDespacho::first();
        $this->assertNotNull($solicitud);
        $this->assertSame('SD-00001', $solicitud->numero);
        $this->assertSame('MDE', $solicitud->sede);
        $this->assertSame('solicitada', $solicitud->estado);
        $this->assertCount(2, $solicitud->detalles);

        $inv = $solicitud->detalles->firstWhere('origen', 'inventario');
        $ext = $solicitud->detalles->firstWhere('origen', 'compra_externa');
        $this->assertSame($item->id, $inv->inventario_id);
        $this->assertNull($ext->inventario_id);
        $this->assertSame('No disponible en almacén', $ext->motivo);
        $this->assertSame('Ferretería X', $ext->proveedor_externo);
    }

    public function test_solicitud_sin_lineas_validas_no_se_crea(): void
    {
        $cliente = Cliente::factory()->create();

        Volt::actingAs($this->vendedor())
            ->test('despacho.form')
            ->set('clienteId', $cliente->id)
            ->set('lineas', [
                ['origen' => 'inventario', 'inventario_id' => null, 'descripcion' => '', 'cantidad' => '', 'proveedor_externo' => '', 'costo_compra_externa' => ''],
            ])
            ->call('enviar')
            ->assertHasErrors();

        $this->assertSame(0, SolicitudDespacho::count());
    }

    public function test_almacenista_no_puede_crear_solicitud(): void
    {
        $almacenista = User::factory()->create(['estado' => 'activo']);
        $almacenista->assignRole(RolPrioridad::Almacenista->value);

        Volt::actingAs($almacenista)
            ->test('despacho.form')
            ->assertForbidden();
    }
}
