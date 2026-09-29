<?php

namespace Tests\Feature\Catalogos;

use App\Enums\RolPrioridad;
use App\Models\Cliente;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ClienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function administrador(): User
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole(RolPrioridad::Administrador->value);

        return $user;
    }

    public function test_administrador_puede_crear_un_cliente(): void
    {
        Volt::actingAs($this->administrador())
            ->test('clientes.index')
            ->call('nuevo')
            ->set('nombre', 'Cliente Nuevo S.A.S')
            ->set('nit', '900999888-1')
            ->set('correo', 'contacto@clientenuevo.com')
            ->call('guardar')
            ->assertSet('mostrarForm', false);

        $this->assertDatabaseHas('clientes', [
            'nombre' => 'Cliente Nuevo S.A.S',
            'nit' => '900999888-1',
        ]);
    }

    public function test_advierte_pero_no_bloquea_nit_duplicado(): void
    {
        Cliente::factory()->create(['nit' => '900111222-3']);

        Volt::actingAs($this->administrador())
            ->test('clientes.index')
            ->call('nuevo')
            ->set('nombre', 'Otra Empresa')
            ->set('nit', '900111222-3')
            ->call('verificarDuplicado')
            ->assertSet('advertenciaDuplicado', 'Ya existe un cliente registrado con el NIT 900111222-3.')
            ->call('guardar')
            ->assertSet('mostrarForm', false);

        $this->assertDatabaseCount('clientes', 2);
    }

    public function test_cliente_inactivo_no_aparece_en_listado_de_activos(): void
    {
        Cliente::factory()->create(['nombre' => 'Cliente Activo', 'estado' => 'activo']);
        Cliente::factory()->create(['nombre' => 'Cliente Inactivo', 'estado' => 'inactivo']);

        Volt::actingAs($this->administrador())
            ->test('clientes.index')
            ->assertSet('filtroEstado', 'activo')
            ->assertSee('Cliente Activo')
            ->assertDontSee('Cliente Inactivo');
    }

    public function test_permite_registrar_varios_correos_separados_por_coma(): void
    {
        Volt::actingAs($this->administrador())
            ->test('clientes.index')
            ->call('nuevo')
            ->set('nombre', 'Cliente Con Varios Contactos')
            ->set('correo', 'uno@ejemplo.com,  dos@ejemplo.com ,uno@ejemplo.com')
            ->call('guardar')
            ->assertSet('mostrarForm', false);

        $cliente = Cliente::where('nombre', 'Cliente Con Varios Contactos')->firstOrFail();

        $this->assertSame(['uno@ejemplo.com', 'dos@ejemplo.com'], $cliente->correosArray());
    }

    public function test_correo_invalido_dentro_de_la_lista_es_rechazado(): void
    {
        Volt::actingAs($this->administrador())
            ->test('clientes.index')
            ->call('nuevo')
            ->set('nombre', 'Cliente Correo Malo')
            ->set('correo', 'valido@ejemplo.com,no-es-un-correo')
            ->call('guardar')
            ->assertHasErrors(['correo']);
    }

    public function test_advierte_duplicado_si_alguno_de_los_correos_ya_existe(): void
    {
        Cliente::factory()->create(['correo' => 'compartido@ejemplo.com']);

        Volt::actingAs($this->administrador())
            ->test('clientes.index')
            ->call('nuevo')
            ->set('nombre', 'Otro Cliente')
            ->set('correo', 'nuevo@ejemplo.com,compartido@ejemplo.com')
            ->call('verificarDuplicado')
            ->assertSet('advertenciaDuplicado', 'Ya existe un cliente registrado con el correo compartido@ejemplo.com.');
    }

    public function test_usuario_sin_permiso_no_puede_ver_clientes(): void
    {
        $tecnico = User::factory()->create(['estado' => 'activo']);
        $tecnico->assignRole(RolPrioridad::Tecnico->value);

        Volt::actingAs($tecnico)
            ->test('clientes.index')
            ->assertForbidden();
    }
}
