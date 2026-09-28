<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Enums\RolPrioridad;
use App\Events\OtCreada;
use App\Models\Cliente;
use App\Models\Equipo;
use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use App\Models\SolicitudInsumoOt;
use App\Models\Tecnico;
use App\Models\User;
use Database\Seeders\EstadosOtSeeder;
use Database\Seeders\PrioridadesSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
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
            ->set('equipoDescripcion', 'Compresor')
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
                ->set('equipoDescripcion', 'Compresor')
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
            ->set('equipoDescripcion', 'Compresor')
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
            ->set('equipoDescripcion', 'Compresor')
            ->set('tareas', [
                ['uid' => 'a', 'descripcion' => 'Tarea', 'tecnico_id' => $tecnico->id, 'insumo_id' => null, 'cantidad_insumo' => ''],
            ])
            ->call('guardar')
            ->assertHasNoErrors();

        Event::assertDispatched(OtCreada::class);
    }

    public function test_la_seccion_de_equipo_esta_bloqueada_hasta_elegir_cliente(): void
    {
        $cliente = Cliente::factory()->create();

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.crear')
            ->assertSee('Selecciona un cliente arriba para registrar los datos del equipo')
            ->assertDontSee('Equipo registrado')
            ->assertDontSee('Tipo de equipo')
            ->set('clienteId', $cliente->id)
            ->assertDontSee('Selecciona un cliente arriba para registrar los datos del equipo')
            ->assertSee('Equipo registrado')
            ->assertSee('Tipo de equipo');
    }

    public function test_selecciona_un_equipo_registrado_del_cliente_y_queda_vinculado(): void
    {
        $cliente = Cliente::factory()->create();
        $tecnico = Tecnico::factory()->conSueldo()->create();
        $equipo = Equipo::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'Compresor', 'marca' => 'Ingersoll', 'serie' => 'SN-99']);

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.crear')
            ->set('clienteId', $cliente->id)
            ->assertSee('Compresor')
            ->set('equipoId', $equipo->id)
            ->set('descripcion', 'Mantenimiento preventivo')
            ->set('tareas', [
                ['uid' => 'a', 'descripcion' => 'Revisión', 'tecnico_id' => $tecnico->id, 'insumo_id' => null, 'cantidad_insumo' => ''],
            ])
            ->call('guardar')
            ->assertHasNoErrors();

        $ot = OrdenTrabajo::first();
        $this->assertSame($equipo->id, $ot->equipo_id);
        $this->assertSame('Compresor', $ot->equipo_descripcion);
        $this->assertSame('Ingersoll', $ot->equipo_marca);
        $this->assertSame('SN-99', $ot->equipo_serie);
    }

    public function test_sin_datos_de_equipo_rechaza_la_creacion(): void
    {
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
            ->assertHasErrors('equipoDescripcion');

        $this->assertSame(0, OrdenTrabajo::count());
        $this->assertSame(0, Equipo::count());
    }

    public function test_llenar_el_equipo_a_mano_lo_registra_y_lo_vincula(): void
    {
        $cliente = Cliente::factory()->create();
        $tecnico = Tecnico::factory()->conSueldo()->create();

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.crear')
            ->set('clienteId', $cliente->id)
            ->set('descripcion', 'Servicio')
            ->set('equipoDescripcion', 'Escalera')
            ->set('equipoMarca', 'Genérica')
            ->set('equipoModelo', 'X1')
            ->set('equipoSerie', 'SN-001')
            ->set('tareas', [
                ['uid' => 'a', 'descripcion' => 'Tarea', 'tecnico_id' => $tecnico->id, 'insumo_id' => null, 'cantidad_insumo' => ''],
            ])
            ->call('guardar')
            ->assertHasNoErrors();

        $ot = OrdenTrabajo::first();
        $equipo = Equipo::first();
        $this->assertSame(1, Equipo::count());
        $this->assertSame($equipo->id, $ot->equipo_id);
        $this->assertSame($cliente->id, $equipo->cliente_id);
        $this->assertSame('Escalera', $equipo->tipo);
        $this->assertSame('X1', $equipo->modelo);
        $this->assertSame('SN-001', $equipo->serie);
    }

    public function test_llenar_a_mano_un_equipo_con_mismo_modelo_y_serie_no_lo_duplica(): void
    {
        $cliente = Cliente::factory()->create();
        $tecnico = Tecnico::factory()->conSueldo()->create();
        $equipo = Equipo::factory()->create([
            'cliente_id' => $cliente->id,
            'tipo' => 'Compresor',
            'modelo' => 'X1',
            'serie' => 'SN-001',
        ]);

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.crear')
            ->set('clienteId', $cliente->id)
            ->set('descripcion', 'Segunda visita')
            ->set('equipoDescripcion', 'Compresor')
            ->set('equipoModelo', 'x1') // mayúsculas/minúsculas no deben importar
            ->set('equipoSerie', 'sn-001')
            ->set('tareas', [
                ['uid' => 'a', 'descripcion' => 'Tarea', 'tecnico_id' => $tecnico->id, 'insumo_id' => null, 'cantidad_insumo' => ''],
            ])
            ->call('guardar')
            ->assertHasNoErrors();

        $ot = OrdenTrabajo::first();
        $this->assertSame(1, Equipo::count(), 'No debe crear un segundo equipo duplicado');
        $this->assertSame($equipo->id, $ot->equipo_id);
    }

    public function test_equipo_manual_sin_serie_no_intenta_emparejar_y_crea_uno_nuevo_cada_vez(): void
    {
        $cliente = Cliente::factory()->create();
        $tecnico = Tecnico::factory()->conSueldo()->create();

        foreach (range(1, 2) as $i) {
            Volt::actingAs($this->jefeDeTaller())
                ->test('ordenes-trabajo.crear')
                ->set('clienteId', $cliente->id)
                ->set('descripcion', "Visita {$i}")
                ->set('equipoDescripcion', 'Taladro')
                ->set('tareas', [
                    ['uid' => 'a', 'descripcion' => 'Tarea', 'tecnico_id' => $tecnico->id, 'insumo_id' => null, 'cantidad_insumo' => ''],
                ])
                ->call('guardar')
                ->assertHasNoErrors();
        }

        $this->assertSame(2, Equipo::count(), 'Sin modelo+serie no hay forma confiable de emparejar');
    }

    public function test_permite_subir_varias_fotos_de_entrada(): void
    {
        Storage::fake('public');
        $cliente = Cliente::factory()->create();
        $tecnico = Tecnico::factory()->conSueldo()->create();

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.crear')
            ->set('clienteId', $cliente->id)
            ->set('descripcion', 'Servicio')
            ->set('equipoDescripcion', 'Compresor')
            ->set('fotosEntrada', [
                UploadedFile::fake()->image('entrada1.jpg'),
                UploadedFile::fake()->image('entrada2.jpg'),
                UploadedFile::fake()->image('entrada3.jpg'),
            ])
            ->set('tareas', [
                ['uid' => 'a', 'descripcion' => 'Tarea', 'tecnico_id' => $tecnico->id, 'insumo_id' => null, 'cantidad_insumo' => ''],
            ])
            ->call('guardar')
            ->assertHasNoErrors();

        $ot = OrdenTrabajo::first();
        $this->assertSame(3, $ot->evidencias()->where('tipo_registro', 'entrada')->count());
    }

    public function test_camara_y_galeria_alimentan_la_misma_lista_de_fotos(): void
    {
        Storage::fake('public');
        $cliente = Cliente::factory()->create();
        $tecnico = Tecnico::factory()->conSueldo()->create();

        $componente = Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.crear')
            ->set('clienteId', $cliente->id)
            ->set('equipoDescripcion', 'Compresor')
            ->set('fotoEntradaCamara', UploadedFile::fake()->image('camara.jpg'))
            ->set('fotosEntradaGaleria', [
                UploadedFile::fake()->image('galeria1.jpg'),
                UploadedFile::fake()->image('galeria2.jpg'),
            ]);

        $this->assertCount(3, $componente->get('fotosEntrada'));
        $this->assertNull($componente->get('fotoEntradaCamara'));
        $this->assertCount(0, $componente->get('fotosEntradaGaleria'));

        $componente->set('descripcion', 'Servicio')
            ->set('tareas', [
                ['uid' => 'a', 'descripcion' => 'Tarea', 'tecnico_id' => $tecnico->id, 'insumo_id' => null, 'cantidad_insumo' => ''],
            ])
            ->call('guardar')
            ->assertHasNoErrors();

        $ot = OrdenTrabajo::first();
        $this->assertSame(3, $ot->evidencias()->where('tipo_registro', 'entrada')->count());
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
