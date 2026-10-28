<?php

namespace Tests\Feature\Cotizaciones;

use App\Enums\RolPrioridad;
use App\Models\SolicitudDespacho;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReporteOtifTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function administrador(): User
    {
        $admin = User::factory()->create(['estado' => 'activo']);
        $admin->assignRole(RolPrioridad::Administrador->value);

        return $admin;
    }

    public function test_muestra_el_otif_calculado_sobre_los_despachos_entregados(): void
    {
        SolicitudDespacho::factory()->create([
            'estado' => 'entregada',
            'fecha_promesa_entrega' => '2026-07-25',
            'entregada_en' => '2026-07-25 10:00:00',
            'entrega_completa' => true,
        ]);
        SolicitudDespacho::factory()->create([
            'estado' => 'entregada',
            'fecha_promesa_entrega' => '2026-07-25',
            'entregada_en' => '2026-07-28 10:00:00',
            'entrega_completa' => false,
        ]);

        $this->actingAs($this->administrador());

        Volt::test('cotizaciones.otif')
            ->assertSee('50%')
            ->assertSee('plan de choque');
    }

    public function test_un_usuario_sin_el_permiso_de_cotizaciones_no_puede_ver_el_otif(): void
    {
        $this->actingAs(User::factory()->create(['estado' => 'activo']));

        $this->get(route('cotizaciones.otif'))->assertForbidden();
    }
}
