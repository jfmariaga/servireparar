<?php

namespace Tests\Feature\Cotizaciones;

use App\Enums\RolPrioridad;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Servicio;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Smoke test de las pantallas de Cotizaciones (spec 006): que rendericen y
 * que las acciones básicas funcionen desde el componente Livewire, no solo
 * desde el Service directamente.
 */
class CotizacionesUiTest extends TestCase
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

    public function test_tablero_lista_cotizaciones(): void
    {
        $admin = $this->administrador();
        Cotizacion::factory()->create(['numero' => 'COT-0001']);

        Volt::actingAs($admin)->test('cotizaciones.tablero')
            ->assertSee('COT-0001');
    }

    public function test_servicios_maestra_crea_un_servicio(): void
    {
        $admin = $this->administrador();

        Volt::actingAs($admin)->test('cotizaciones.servicios-maestra')
            ->call('nueva')
            ->set('nombre', 'Diagnóstico general')
            ->set('costoUnitario', '50000')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('servicios', ['nombre' => 'Diagnóstico general']);
    }

    public function test_gestionar_permite_construir_y_enviar_desde_la_ui(): void
    {
        Mail::fake();
        $admin = $this->administrador();
        $cliente = Cliente::factory()->create(['correo' => 'ui@cliente.test']);
        $cotizacion = Cotizacion::factory()->create(['cliente_id' => $cliente->id]);
        $servicio = Servicio::factory()->create(['costo_unitario' => 40000]);

        $comp = Volt::actingAs($admin)->test('cotizaciones.gestionar', ['cotizacion' => $cotizacion]);
        $comp->call('agregarItem')
            ->set('items.0.tipo_item', 'servicio')
            ->set('items.0.servicio_id', $servicio->id)
            ->set('items.0.cantidad', 2)
            ->call('guardarItems')
            ->assertHasNoErrors();

        $this->assertEquals(80000, $cotizacion->fresh()->total);

        $comp->call('enviar')->assertHasNoErrors();
        $this->assertSame('cotizada', $cotizacion->fresh()->estado);
    }

    public function test_gestionar_permite_asignar_cliente_manualmente(): void
    {
        $admin = $this->administrador();
        $cliente = Cliente::factory()->create();
        $cotizacion = Cotizacion::factory()->create(['cliente_id' => null]);

        Volt::actingAs($admin)->test('cotizaciones.gestionar', ['cotizacion' => $cotizacion])
            ->set('clienteIdAsignar', $cliente->id)
            ->call('asignarCliente')
            ->assertHasNoErrors();

        $this->assertSame($cliente->id, $cotizacion->fresh()->cliente_id);
    }

    public function test_solo_administrador_puede_abrir_el_tablero(): void
    {
        $tecnico = User::factory()->create(['estado' => 'activo']);
        $tecnico->assignRole(RolPrioridad::Tecnico->value);

        Volt::actingAs($tecnico)->test('cotizaciones.tablero')->assertForbidden();
    }
}
