<?php

namespace Tests\Feature\Personal;

use App\Enums\RolPrioridad;
use App\Models\Especialidad;
use App\Models\Tecnico;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * La ficha de técnico (spec 004) se gestiona desde la pantalla de Usuarios
 * (spec 001, FR-012) — no existe una pantalla separada de "Empleados".
 */
class TecnicoTest extends TestCase
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

    public function test_administrador_crea_usuario_tecnico_con_ficha_de_especialidad(): void
    {
        $especialidad = Especialidad::factory()->create(['nombre' => 'Refrigeración']);

        Volt::actingAs($this->administrador())
            ->test('admin.usuarios.index')
            ->call('nuevo')
            ->set('name', 'Juan Técnico')
            ->set('email', 'juan.tecnico@servireparar.com')
            ->set('password', 'password123')
            ->set('roles', [RolPrioridad::Tecnico->value])
            ->set('especialidadId', $especialidad->id)
            ->set('sueldo', '2400000')
            ->set('cargo', 'Técnico de taller')
            ->set('tipoContrato', 'indefinido')
            ->call('guardar')
            ->assertSet('mostrarForm', false)
            ->assertHasNoErrors();

        $usuario = User::where('email', 'juan.tecnico@servireparar.com')->firstOrFail();

        $this->assertTrue($usuario->hasRole(RolPrioridad::Tecnico->value));

        $tecnico = Tecnico::where('usuario_id', $usuario->id)->first();
        $this->assertNotNull($tecnico);
        $this->assertSame($especialidad->id, $tecnico->especialidad_id);
        $this->assertSame('Técnico de taller', $tecnico->cargo);
        $this->assertSame('indefinido', $tecnico->tipo_contrato);
        $this->assertEquals(2400000, $tecnico->sueldoVigente());
        $this->assertEquals(80000, $tecnico->valorDia()); // 2.400.000 / 30
        $this->assertTrue($tecnico->activo);
    }

    public function test_especialidad_es_obligatoria_para_el_rol_tecnico(): void
    {
        Volt::actingAs($this->administrador())
            ->test('admin.usuarios.index')
            ->call('nuevo')
            ->set('name', 'Sin Especialidad')
            ->set('email', 'sinespecialidad@servireparar.com')
            ->set('password', 'password123')
            ->set('roles', [RolPrioridad::Tecnico->value])
            ->call('guardar')
            ->assertHasErrors(['especialidadId' => 'required']);
    }

    public function test_usuario_sin_rol_tecnico_no_genera_ficha_de_tecnico(): void
    {
        Volt::actingAs($this->administrador())
            ->test('admin.usuarios.index')
            ->call('nuevo')
            ->set('name', 'Almacenista Normal')
            ->set('email', 'almacenista@servireparar.com')
            ->set('password', 'password123')
            ->set('roles', [RolPrioridad::Almacenista->value])
            ->call('guardar')
            ->assertHasNoErrors();

        $usuario = User::where('email', 'almacenista@servireparar.com')->firstOrFail();

        $this->assertNull(Tecnico::where('usuario_id', $usuario->id)->first());
    }

    public function test_tecnico_inactivo_queda_excluido_del_scope_disponibles(): void
    {
        $activo = Tecnico::factory()->create(['activo' => true]);
        $inactivo = Tecnico::factory()->create(['activo' => false]);

        $disponibles = Tecnico::disponibles()->pluck('id');

        $this->assertTrue($disponibles->contains($activo->id));
        $this->assertFalse($disponibles->contains($inactivo->id));
    }

    public function test_metricas_de_tecnico_inactivado_se_conservan_en_su_ficha(): void
    {
        $tecnico = Tecnico::factory()->conSueldo(3000000)->create(['activo' => true]);

        $tecnico->update(['activo' => false]);

        $this->assertEquals(3000000, $tecnico->fresh()->sueldoVigente());
        $this->assertNotNull(Tecnico::find($tecnico->id));
    }
}
