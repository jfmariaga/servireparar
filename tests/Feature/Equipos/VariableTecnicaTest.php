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
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 005, US2 (FR-004): el técnico registra variables técnicas al ejecutar
 * una tarea en curso sobre un equipo, desde la vista de detalle de la OT
 * (spec 002) — igual criterio que la evidencia y la observación de la tarea.
 */
class VariableTecnicaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    /** @return array{0: OrdenTrabajo, 1: User, 2: DetalleOt} */
    private function crearOtEnCursoConTecnico(): array
    {
        $cliente = Cliente::factory()->create();
        $equipo = Equipo::factory()->create(['cliente_id' => $cliente->id, 'tipo' => 'Compresor']);

        $usuario = User::factory()->create(['estado' => 'activo']);
        $usuario->assignRole(RolPrioridad::Tecnico->value);
        $tecnico = Tecnico::factory()->for($usuario, 'usuario')->create();

        $ot = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create([
            'cliente_id' => $cliente->id,
            'equipo_id' => $equipo->id,
        ]);
        $tarea = DetalleOt::factory()->for($ot, 'ordenTrabajo')->create([
            'tecnico_id' => $tecnico->id,
            'estado_tarea' => 'en_curso',
        ]);

        return [$ot, $usuario, $tarea];
    }

    public function test_tecnico_registra_una_variable_tecnica_al_ejecutar_la_tarea(): void
    {
        [$ot, $usuario, $tarea] = $this->crearOtEnCursoConTecnico();

        Volt::actingAs($usuario)
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->set("variableForm.{$tarea->id}.nombre", 'Presión')
            ->set("variableForm.{$tarea->id}.valor", '32')
            ->set("variableForm.{$tarea->id}.unidad", 'psi')
            ->call('agregarVariableTecnica', $tarea->id)
            ->assertHasNoErrors()
            ->assertSee('Presión');

        $this->assertDatabaseHas('variables_tecnicas', [
            'ot_id' => $ot->id,
            'detalle_ot_id' => $tarea->id,
            'nombre' => 'Presión',
            'valor' => '32',
            'unidad' => 'psi',
            'registrado_por' => $usuario->id,
        ]);
    }

    public function test_nombre_y_valor_son_obligatorios(): void
    {
        [$ot, $usuario, $tarea] = $this->crearOtEnCursoConTecnico();

        Volt::actingAs($usuario)
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('agregarVariableTecnica', $tarea->id)
            ->assertHasErrors(["variableForm.{$tarea->id}.nombre", "variableForm.{$tarea->id}.valor"]);
    }

    public function test_no_se_puede_registrar_en_una_tarea_que_no_esta_en_curso(): void
    {
        [$ot, $usuario, $tarea] = $this->crearOtEnCursoConTecnico();
        $tarea->update(['estado_tarea' => 'pendiente']);

        Volt::actingAs($usuario)
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot->fresh()])
            ->set("variableForm.{$tarea->id}.nombre", 'Presión')
            ->set("variableForm.{$tarea->id}.valor", '32')
            ->call('agregarVariableTecnica', $tarea->id);

        $this->assertSame(0, \App\Models\VariableTecnica::count());
    }

    public function test_no_se_muestra_el_bloque_si_la_ot_no_tiene_equipo_vinculado(): void
    {
        $ot = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create(['equipo_id' => null]);

        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole(RolPrioridad::Administrador->value);

        Volt::actingAs($admin)
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->assertDontSee('Variables técnicas de');
    }
}
