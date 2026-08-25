<?php

namespace Tests\Feature\Inventario;

use App\Enums\RolPrioridad;
use App\Models\AjusteAuditoria;
use App\Models\AuditoriaInventario;
use App\Models\Inventario;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuditoriaFlujoTest extends TestCase
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

    private function administrador(): User
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole(RolPrioridad::Administrador->value);

        return $user;
    }

    public function test_almacenista_inicia_auditoria_y_registra_conteo(): void
    {
        $item = Inventario::factory()->create(['stock_actual' => 100]);

        Volt::actingAs($this->almacenista())
            ->test('inventario.auditoria')
            ->call('iniciarAuditoria')
            ->call('nuevoConteo')
            ->set('inventarioId', $item->id)
            ->set('stockFisico', '90')
            ->set('motivo', 'Conteo físico bajo demanda')
            ->call('registrarConteo')
            ->assertHasNoErrors();

        $ajuste = AjusteAuditoria::where('inventario_id', $item->id)->first();
        $this->assertNotNull($ajuste);
        $this->assertSame('pendiente', $ajuste->estado);
        $this->assertEquals(100, $item->fresh()->stock_actual);
    }

    public function test_almacenista_no_puede_aprobar_su_propio_ajuste(): void
    {
        $almacenista = $this->almacenista();
        $ajuste = AjusteAuditoria::factory()->create();

        $this->assertFalse($almacenista->can('approve', $ajuste));
    }

    public function test_administrador_aprueba_ajuste_desde_la_pantalla_de_auditoria(): void
    {
        $item = Inventario::factory()->create(['stock_actual' => 100]);
        $ajuste = AjusteAuditoria::factory()->create([
            'inventario_id' => $item->id,
            'stock_sistema' => 100,
            'stock_fisico' => 88,
        ]);

        Volt::actingAs($this->administrador())
            ->test('inventario.auditoria')
            ->call('aprobar', $ajuste->id);

        $this->assertEquals(88, $item->fresh()->stock_actual);
        $this->assertSame('aprobado', $ajuste->fresh()->estado);
    }

    public function test_cancelar_auditoria_la_marca_cancelada_y_rechaza_ajustes_pendientes(): void
    {
        $auditoria = AuditoriaInventario::factory()->create(['estado' => 'abierta']);
        $ajuste = AjusteAuditoria::factory()->create(['auditoria_id' => $auditoria->id, 'estado' => 'pendiente']);

        Volt::actingAs($this->administrador())
            ->test('inventario.auditoria')
            ->call('cancelarAuditoria', $auditoria->id);

        $this->assertSame('cancelada', $auditoria->fresh()->estado);
        $this->assertNotNull($auditoria->fresh()->fecha_cierre);
        $this->assertSame('rechazado', $ajuste->fresh()->estado);
    }

    public function test_auditoria_cancelada_no_aparece_como_auditoria_abierta(): void
    {
        $auditoria = AuditoriaInventario::factory()->create(['estado' => 'abierta']);

        Volt::actingAs($this->administrador())
            ->test('inventario.auditoria')
            ->call('cancelarAuditoria', $auditoria->id);

        $this->assertNull(AuditoriaInventario::abiertas()->find($auditoria->id));
    }
}
