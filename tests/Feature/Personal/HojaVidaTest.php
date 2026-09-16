<?php

namespace Tests\Feature\Personal;

use App\Enums\RolPrioridad;
use App\Models\Especialidad;
use App\Models\Tecnico;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Hoja de vida del técnico (spec 004, FR-013): panel de solo lectura abierto
 * desde la lista de Usuarios.
 */
class HojaVidaTest extends TestCase
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

    public function test_muestra_sueldo_actual_valor_dia_datos_laborales_e_historico(): void
    {
        $especialidad = Especialidad::factory()->create(['nombre' => 'Refrigeración']);
        $usuario = User::factory()->create(['name' => 'Pedro Técnico', 'estado' => 'activo']);
        $usuario->assignRole(RolPrioridad::Tecnico->value);

        $tecnico = Tecnico::factory()->for($usuario, 'usuario')->create([
            'especialidad_id' => $especialidad->id,
            'cargo' => 'Técnico senior',
            'tipo_contrato' => 'indefinido',
        ]);
        $tecnico->registrarSueldo(2_100_000, Carbon::parse('2026-01-01'));
        $tecnico->registrarSueldo(2_700_000, Carbon::parse('2026-06-01'));

        Volt::actingAs($this->administrador())
            ->test('admin.usuarios.index')
            ->call('verHojaVida', $usuario->id)
            ->assertSet('hojaVidaUserId', $usuario->id)
            ->assertSee('Hoja de vida')
            ->assertSee('Refrigeración')
            ->assertSee('Técnico senior')
            ->assertSee('$ 2.700.000')      // sueldo actual
            ->assertSee('$ 90.000')         // valor día = 2.700.000 / 30
            ->assertSee('$ 2.100.000')      // fila del histórico
            ->assertSee('Resumen operativo')
            ->assertSee('Carga actual');
    }

    public function test_no_hay_hoja_de_vida_para_usuarios_sin_ficha_de_tecnico(): void
    {
        $usuario = User::factory()->create(['estado' => 'activo']);
        $usuario->assignRole(RolPrioridad::Almacenista->value);

        Volt::actingAs($this->administrador())
            ->test('admin.usuarios.index')
            ->call('verHojaVida', $usuario->id)
            ->assertDontSee('Histórico de sueldos');
    }
}
