<?php

namespace Tests\Feature\Equipos;

use App\Enums\RolPrioridad;
use App\Models\Cliente;
use App\Models\DetalleOt;
use App\Models\EstadoOt;
use App\Models\Equipo;
use App\Models\OrdenTrabajo;
use App\Models\Tecnico;
use App\Models\User;
use App\Models\VariableTecnica;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 005, US2 (FR-003/FR-004): historial técnico reconstruido desde las OT
 * del equipo, sin fuentes externas (SC-001), incluyendo variables técnicas.
 */
class HistorialTecnicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function jefeDeTaller(): User
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole(RolPrioridad::JefeDeTaller->value);

        return $user;
    }

    public function test_administrador_ve_el_historial_con_tecnico_fecha_y_evidencias(): void
    {
        $cliente = Cliente::factory()->create();
        $equipo = Equipo::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'Compresor']);

        $usuario = User::factory()->create(['name' => 'Luis Técnico', 'estado' => 'activo']);
        $usuario->assignRole(RolPrioridad::Tecnico->value);
        $tecnico = Tecnico::factory()->for($usuario, 'usuario')->create();

        $ot = OrdenTrabajo::factory()->enEstado(EstadoOt::FINALIZADA)->create([
            'cliente_id' => $cliente->id,
            'equipo_id' => $equipo->id,
            'descripcion' => 'Mantenimiento preventivo trimestral',
        ]);
        DetalleOt::factory()->for($ot, 'ordenTrabajo')->create([
            'tecnico_id' => $tecnico->id,
            'estado_tarea' => 'finalizada',
        ]);

        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole(RolPrioridad::Administrador->value);

        Volt::actingAs($admin)
            ->test('equipos.historial', ['equipo' => $equipo])
            ->assertSee($ot->numero_ot)
            ->assertSee('Luis Técnico')
            ->assertSee('Mantenimiento preventivo trimestral');
    }

    public function test_registrar_variable_tecnica_queda_visible_en_el_historial(): void
    {
        $cliente = Cliente::factory()->create();
        $equipo = Equipo::factory()->create(['cliente_id' => $cliente->id]);

        $ot = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create([
            'cliente_id' => $cliente->id,
            'equipo_id' => $equipo->id,
        ]);

        VariableTecnica::factory()->create([
            'ot_id' => $ot->id,
            'nombre' => 'Temperatura',
            'valor' => '18.5',
            'unidad' => '°C',
        ]);

        Volt::actingAs($this->jefeDeTaller())
            ->test('equipos.historial', ['equipo' => $equipo])
            ->assertSee('Temperatura')
            ->assertSee('18.5');
    }

    public function test_tecnico_sin_tareas_en_el_equipo_no_puede_ver_el_historial(): void
    {
        $equipo = Equipo::factory()->create();

        $tecnico = User::factory()->create(['estado' => 'activo']);
        $tecnico->assignRole(RolPrioridad::Tecnico->value);
        Tecnico::factory()->for($tecnico, 'usuario')->create();

        Volt::actingAs($tecnico)
            ->test('equipos.historial', ['equipo' => $equipo])
            ->assertForbidden();
    }

    public function test_tecnico_con_una_tarea_en_el_equipo_si_puede_ver_el_historial(): void
    {
        $cliente = Cliente::factory()->create();
        $equipo = Equipo::factory()->create(['cliente_id' => $cliente->id]);

        $usuario = User::factory()->create(['estado' => 'activo']);
        $usuario->assignRole(RolPrioridad::Tecnico->value);
        $tecnico = Tecnico::factory()->for($usuario, 'usuario')->create();

        $ot = OrdenTrabajo::factory()->create(['cliente_id' => $cliente->id, 'equipo_id' => $equipo->id]);
        DetalleOt::factory()->for($ot, 'ordenTrabajo')->create(['tecnico_id' => $tecnico->id]);

        Volt::actingAs($usuario)
            ->test('equipos.historial', ['equipo' => $equipo])
            ->assertSuccessful();
    }

    public function test_equipo_sin_ot_muestra_mensaje_vacio(): void
    {
        $equipo = Equipo::factory()->create();

        Volt::actingAs($this->jefeDeTaller())
            ->test('equipos.historial', ['equipo' => $equipo])
            ->assertSee('todavía no tiene');
    }
}
