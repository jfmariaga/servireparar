<?php

namespace Tests\Feature\Equipos;

use App\Enums\RolPrioridad;
use App\Models\Equipo;
use App\Models\MantenimientoPreventivo;
use App\Models\User;
use App\Services\Equipos\MantenimientoPreventivoService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec 005 (FR-006) → spec 008 (campana): un mantenimiento próximo a vencer
 * avisa a Administrador y Jefe de Taller, y no se repite una vez disparado.
 */
class NotificacionesEquiposTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function conRol(RolPrioridad $rol): User
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole($rol->value);

        return $user;
    }

    public function test_mantenimiento_proximo_a_vencer_avisa_a_administrador_y_jefe_de_taller(): void
    {
        $admin = $this->conRol(RolPrioridad::Administrador);
        $jefe = $this->conRol(RolPrioridad::JefeDeTaller);
        $almacenista = $this->conRol(RolPrioridad::Almacenista);

        $equipo = Equipo::factory()->create(['tipo' => 'Compresor', 'marca' => 'Ingersoll']);
        MantenimientoPreventivo::factory()->for($equipo)->create(['proxima_fecha' => now()->addDays(3)]);

        $disparadas = app(MantenimientoPreventivoService::class)->revisarVencimientos();

        $this->assertSame(1, $disparadas);
        $this->assertSame(1, $admin->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $jefe->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $almacenista->fresh()->unreadNotifications()->count());
        $this->assertStringContainsString('Compresor', $admin->fresh()->unreadNotifications()->first()->data['cuerpo']);
    }

    public function test_no_se_repite_el_aviso_una_vez_disparado(): void
    {
        $admin = $this->conRol(RolPrioridad::Administrador);
        $equipo = Equipo::factory()->create();
        MantenimientoPreventivo::factory()->for($equipo)->create(['proxima_fecha' => now()->addDays(2)]);

        $servicio = app(MantenimientoPreventivoService::class);
        $servicio->revisarVencimientos();
        $servicio->revisarVencimientos();

        $this->assertSame(1, $admin->fresh()->unreadNotifications()->count());
    }
}
