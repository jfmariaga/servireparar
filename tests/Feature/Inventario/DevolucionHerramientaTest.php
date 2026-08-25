<?php

namespace Tests\Feature\Inventario;

use App\Enums\RolPrioridad;
use App\Models\Inventario;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DevolucionHerramientaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function almacenista(): User
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole(RolPrioridad::Almacenista->value);

        return $user;
    }

    public function test_devolucion_en_buen_estado_deja_la_herramienta_disponible(): void
    {
        $herramienta = Inventario::factory()->herramienta()->create(['estado_herramienta' => 'en_uso']);

        Volt::actingAs($this->almacenista())
            ->test('inventario.movimientos')
            ->call('iniciarDevolucion', $herramienta->id)
            ->set('nuevoEstadoHerramienta', 'disponible')
            ->call('confirmarDevolucion');

        $this->assertSame('disponible', $herramienta->fresh()->estado_herramienta);
    }

    public function test_devolucion_en_mal_estado_queda_excluida_de_nuevas_asignaciones(): void
    {
        $herramienta = Inventario::factory()->herramienta()->create(['estado_herramienta' => 'en_uso']);

        Volt::actingAs($this->almacenista())
            ->test('inventario.movimientos')
            ->call('iniciarDevolucion', $herramienta->id)
            ->set('nuevoEstadoHerramienta', 'dañada')
            ->call('confirmarDevolucion');

        $this->assertSame('dañada', $herramienta->fresh()->estado_herramienta);
        $this->assertNotSame('en_uso', $herramienta->fresh()->estado_herramienta);
    }
}
