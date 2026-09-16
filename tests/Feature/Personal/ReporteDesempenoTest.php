<?php

namespace Tests\Feature\Personal;

use App\Enums\RolPrioridad;
use App\Models\DetalleOt;
use App\Models\Tecnico;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Pantalla `/personal/desempeno` (spec 004, US3, T016/T017): reporte agregado
 * de desempeño por técnico, restringido a Administrador.
 */
class ReporteDesempenoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_administrador_ve_el_reporte_con_tecnicos_con_historial(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana Técnica', 'estado' => 'activo']);
        $usuario->assignRole(RolPrioridad::Tecnico->value);
        $tecnico = Tecnico::factory()->for($usuario, 'usuario')->create();

        DetalleOt::factory()->create([
            'tecnico_id' => $tecnico->id,
            'estado_tarea' => 'finalizada',
            'dias_trabajados' => 2,
            'fecha_fin' => now(),
        ]);

        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole(RolPrioridad::Administrador->value);

        Volt::actingAs($admin)
            ->test('personal.desempeno')
            ->assertSee('Ana Técnica')
            ->assertSee('Desempeño');
    }

    public function test_tecnico_no_puede_ver_el_reporte(): void
    {
        $usuario = User::factory()->create(['estado' => 'activo']);
        $usuario->assignRole(RolPrioridad::Tecnico->value);
        Tecnico::factory()->for($usuario, 'usuario')->create();

        Volt::actingAs($usuario)
            ->test('personal.desempeno')
            ->assertForbidden();
    }
}
