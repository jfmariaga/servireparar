<?php

namespace Tests\Feature\Despacho;

use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\SolicitudDespacho;
use App\Services\Inventario\DespachoService;
use App\Services\Inventario\MovimientoService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DespachoPolicyTest extends TestCase
{
    use DespachoTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Mail::fake();
    }

    private function solicitudRemisionada(): SolicitudDespacho
    {
        $despachos = app(DespachoService::class);
        $almacen = $this->almacenista();
        $cliente = Cliente::factory()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 0, 'costo_unitario' => 0]);
        app(MovimientoService::class)->entrada($item, 20, $almacen, costoUnitario: 100);

        $solicitud = $despachos->crear($this->vendedor(), $cliente->id, null, [
            ['origen' => 'inventario', 'inventario_id' => $item->id, 'descripcion' => '', 'cantidad' => '2', 'proveedor_externo' => '', 'costo_compra_externa' => ''],
        ]);
        $despachos->recibir($solicitud, $almacen);
        $despachos->generarRemision($solicitud, $almacen);

        return $solicitud;
    }

    public function test_vendedor_no_puede_gestionar_la_entrega_en_almacen(): void
    {
        $solicitud = $this->solicitudRemisionada();

        Volt::actingAs($this->vendedor())
            ->test('despacho.entrega', ['solicitud' => $solicitud])
            ->call('confirmarEntrega')
            ->assertForbidden();
    }

    public function test_almacenista_no_puede_crear_solicitud(): void
    {
        Volt::actingAs($this->almacenista())
            ->test('despacho.form')
            ->assertForbidden();
    }

    public function test_vendedor_y_almacenista_pueden_anular(): void
    {
        foreach ([$this->vendedor(), $this->almacenista()] as $actor) {
            $solicitud = $this->solicitudRemisionada();

            Volt::actingAs($actor)
                ->test('despacho.entrega', ['solicitud' => $solicitud])
                ->call('anular')
                ->assertHasNoErrors();

            $this->assertSame('anulada', $solicitud->fresh()->estado);
        }
    }

    public function test_tecnico_no_puede_ver_la_bandeja_de_despachos(): void
    {
        $tecnico = \App\Models\User::factory()->create(['estado' => 'activo']);
        $tecnico->assignRole(\App\Enums\RolPrioridad::Tecnico->value);

        $this->actingAs($tecnico)->get(route('despachos.index'))->assertForbidden();
    }
}
