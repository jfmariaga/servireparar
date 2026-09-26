<?php

namespace Tests\Feature\Notificaciones;

use App\Enums\RolPrioridad;
use App\Services\Notificaciones\DestinatariosPorRolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\OrdenesTrabajo\OtScenario;
use Tests\TestCase;

/**
 * Deduplicación multi-rol (spec 008, FR-002 / T013): un usuario con 2 roles
 * aplicables recibe una sola vez la notificación, no una por rol.
 */
class DestinatariosPorRolServiceTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    public function test_un_usuario_con_dos_roles_aparece_una_sola_vez(): void
    {
        $multiRol = $this->usuarioConRol(RolPrioridad::Administrador->value);
        $multiRol->assignRole(RolPrioridad::JefeDeTaller->value);
        $soloJefe = $this->jefeDeTaller();

        $destinatarios = app(DestinatariosPorRolService::class)->resolver([
            RolPrioridad::Administrador->value,
            RolPrioridad::JefeDeTaller->value,
        ]);

        $this->assertSame(1, $destinatarios->where('id', $multiRol->id)->count());
        $this->assertTrue($destinatarios->pluck('id')->contains($soloJefe->id));
        $this->assertSame(2, $destinatarios->count());
    }

    public function test_ademas_de_no_duplica_si_ya_viene_por_rol(): void
    {
        $jefe = $this->jefeDeTaller();

        $destinatarios = app(DestinatariosPorRolService::class)->resolver(
            [RolPrioridad::JefeDeTaller->value],
            [$jefe],
        );

        $this->assertSame(1, $destinatarios->count());
    }

    public function test_ignora_usuarios_inactivos(): void
    {
        $jefe = $this->jefeDeTaller();
        $jefe->update(['estado' => 'inactivo']);

        $destinatarios = app(DestinatariosPorRolService::class)->resolver([RolPrioridad::JefeDeTaller->value]);

        $this->assertSame(0, $destinatarios->count());
    }
}
