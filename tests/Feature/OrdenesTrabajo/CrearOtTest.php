<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Enums\RolPrioridad;
use App\Events\OtCreada;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use App\Models\SolicitudInsumoOt;
use App\Models\Tecnico;
use App\Models\User;
use Database\Seeders\EstadosOtSeeder;
use Database\Seeders\PrioridadesSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CrearOtTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesSeeder::class, PrioridadesSeeder::class, EstadosOtSeeder::class]);
    }

    private function jefeDeTaller(): User
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole(RolPrioridad::JefeDeTaller->value);

        return $user;
    }

    public function test_crea_una_ot_valida_con_numeracion_otsv(): void
    {
        $cliente = Cliente::factory()->create();
        $tecnico = Tecnico::factory()->conSueldo()->create();

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.crear')
            ->set('clienteId', $cliente->id)
            ->set('descripcion', 'Mantenimiento correctivo de compresor')
            ->set('tareas', [
                ['uid' => 'a', 'descripcion' => 'Desmontar cabezote', 'tecnico_id' => $tecnico->id, 'insumo_id' => null, 'cantidad_insumo' => ''],
            ])
            ->call('guardar')
            ->assertHasNoErrors();

        $ot = OrdenTrabajo::first();
        $this->assertNotNull($ot);
        $this->assertSame('OTSV-00001', $ot->numero_ot);
        $this->assertSame('en_revision', $ot->estado->slug);
        $this->assertCount(1, $ot->tareas);
        $this->assertDatabaseHas('ot_eventos', ['ot_id' => $ot->id, 'tipo' => 'creacion']);
    }

    public function test_numeracion_es_consecutiva(): void
    {
        $cliente = Cliente::factory()->create();
        $tecnico = Tecnico::factory()->conSueldo()->create();

        foreach (['OTSV-00001', 'OTSV-00002'] as $esperado) {
            Volt::actingAs($this->jefeDeTaller())
                ->test('ordenes-trabajo.crear')
                ->set('clienteId', $cliente->id)
                ->set('descripcion', 'Servicio')
                ->set('tareas', [
                    ['uid' => 'a', 'descripcion' => 'Tarea', 'tecnico_id' => $tecnico->id, 'insumo_id' => null, 'cantidad_insumo' => ''],
                ])
                ->call('guardar')
                ->assertHasNoErrors();
        }

        $this->assertSame(['OTSV-00001', 'OTSV-00002'], OrdenTrabajo::orderBy('id')->pluck('numero_ot')->all());
    }

    public function test_rechaza_creacion_sin_tarea_con_tecnico(): void
    {
        $cliente = Cliente::factory()->create();

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.crear')
            ->set('clienteId', $cliente->id)
            ->set('descripcion', 'Servicio sin tareas')
            ->set('tareas', [
                ['uid' => 'a', 'descripcion' => 'Tarea sin técnico', 'tecnico_id' => null, 'insumo_id' => null, 'cantidad_insumo' => ''],
            ])
            ->call('guardar')
            ->assertHasErrors('tareas.0.tecnico_id');

        $this->assertSame(0, OrdenTrabajo::count());
    }

    public function test_tarea_con_insumo_genera_solicitud_hacia_inventario(): void
    {
        $cliente = Cliente::factory()->create();
        $tecnico = Tecnico::factory()->conSueldo()->create();
        $insumo = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 50]);

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.crear')
            ->set('clienteId', $cliente->id)
            ->set('descripcion', 'Cambio de sellos')
            ->set('tareas', [
                ['uid' => 'a', 'descripcion' => 'Reemplazo', 'tecnico_id' => $tecnico->id, 'insumos' => [['inventario_id' => $insumo->id, 'cantidad' => '4']]],
            ])
            ->call('guardar')
            ->assertHasNoErrors();

        $solicitud = SolicitudInsumoOt::first();
        $this->assertNotNull($solicitud);
        $this->assertSame('pendiente', $solicitud->estado);
        $this->assertEquals(4, (float) $solicitud->cantidad);
        $this->assertSame($insumo->id, $solicitud->inventario_id);
        $this->assertSame(0, \App\Models\MovimientoInventario::count(), 'No debe descontar stock al crear la OT');
    }

    public function test_dispara_evento_ot_creada(): void
    {
        Event::fake([OtCreada::class]);
        $cliente = Cliente::factory()->create();
        $tecnico = Tecnico::factory()->conSueldo()->create();

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.crear')
            ->set('clienteId', $cliente->id)
            ->set('descripcion', 'Servicio')
            ->set('tareas', [
                ['uid' => 'a', 'descripcion' => 'Tarea', 'tecnico_id' => $tecnico->id, 'insumo_id' => null, 'cantidad_insumo' => ''],
            ])
            ->call('guardar')
            ->assertHasNoErrors();

        Event::assertDispatched(OtCreada::class);
    }

    public function test_tecnico_no_puede_abrir_el_formulario_de_creacion(): void
    {
        $tecnicoUser = User::factory()->create(['estado' => 'activo']);
        $tecnicoUser->assignRole(RolPrioridad::Tecnico->value);

        Volt::actingAs($tecnicoUser)
            ->test('ordenes-trabajo.crear')
            ->assertForbidden();
    }
}
