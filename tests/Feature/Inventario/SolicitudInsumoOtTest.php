<?php

namespace Tests\Feature\Inventario;

use App\Enums\RolPrioridad;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\SolicitudInsumoOt;
use App\Models\Tecnico;
use App\Models\User;
use App\Services\OrdenTrabajo\CosteoOtService;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\Feature\OrdenesTrabajo\OtScenario;
use Tests\TestCase;

class SolicitudInsumoOtTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    private function almacenista(): User
    {
        $u = User::factory()->create(['estado' => 'activo']);
        $u->assignRole(RolPrioridad::Almacenista->value);

        return $u;
    }

    /**
     * Crea una OT con una tarea que pide `$cantidad` de un consumible con `$stock`.
     *
     * @return array{0: \App\Models\OrdenTrabajo, 1: SolicitudInsumoOt, 2: Inventario}
     */
    private function otConSolicitud(float $stock = 50, float $cantidad = 3, float $costo = 5000): array
    {
        $ot = $this->crearOt(tareas: 1);
        $tecnico = Tecnico::factory()->conSueldo()->create();
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => $stock, 'costo_unitario' => $costo]);

        app(OrdenTrabajoService::class)->agregarTarea($ot, $this->jefeDeTaller(), [
            'descripcion' => 'Tarea con insumo',
            'tecnico_id' => $tecnico->id,
            'insumo_id' => $item->id,
            'cantidad_insumo' => $cantidad,
        ]);

        $solicitud = SolicitudInsumoOt::where('ot_id', $ot->id)->firstOrFail();

        return [$ot->fresh(), $solicitud, $item];
    }

    public function test_almacenista_entrega_el_insumo_descuenta_stock_y_enlaza_el_movimiento(): void
    {
        [$ot, $solicitud, $item] = $this->otConSolicitud(stock: 50, cantidad: 3);

        Volt::actingAs($this->almacenista())
            ->test('inventario.solicitudes-ot')
            ->call('entregar', $solicitud->id)
            ->assertHasNoErrors();

        $solicitud->refresh();
        $this->assertSame('entregada', $solicitud->estado);
        $this->assertNotNull($solicitud->movimiento_id);
        $this->assertEquals(47, (float) $item->fresh()->stock_actual);

        $mov = MovimientoInventario::find($solicitud->movimiento_id);
        $this->assertSame('ot', $mov->origen);
        $this->assertSame('salida', $mov->tipo_mov);
        $this->assertSame($ot->numero_ot, $mov->referencia);
        $this->assertDatabaseHas('ot_eventos', ['ot_id' => $ot->id, 'tipo' => 'insumo_entregado']);
    }

    public function test_stock_insuficiente_bloquea_la_entrega(): void
    {
        // Reserva válida (stock 5 ≥ 5) y luego el stock baja (ajuste/auditoría):
        // la entrega debe seguir bloqueada por MovimientoService::salida.
        [$ot, $solicitud, $item] = $this->otConSolicitud(stock: 5, cantidad: 5);
        $item->update(['stock_actual' => 1]);

        Volt::actingAs($this->almacenista())
            ->test('inventario.solicitudes-ot')
            ->call('entregar', $solicitud->id);

        $this->assertSame('pendiente', $solicitud->fresh()->estado);
        $this->assertEquals(1, (float) $item->fresh()->stock_actual);
        $this->assertSame(0, MovimientoInventario::where('origen', 'ot')->count());
    }

    public function test_rechazo_con_motivo_marca_la_solicitud_y_deja_traza(): void
    {
        [$ot, $solicitud] = $this->otConSolicitud();

        Volt::actingAs($this->almacenista())
            ->test('inventario.solicitudes-ot')
            ->call('pedirRechazo', $solicitud->id)
            ->set('motivoRechazo', 'Ese ítem se pide por compra directa')
            ->call('rechazar')
            ->assertHasNoErrors();

        $solicitud->refresh();
        $this->assertSame('rechazada', $solicitud->estado);
        $this->assertSame('Ese ítem se pide por compra directa', $solicitud->motivo_rechazo);
        $this->assertDatabaseHas('ot_eventos', ['ot_id' => $ot->id, 'tipo' => 'insumo_rechazado']);
    }

    public function test_el_costeo_de_la_ot_toma_el_costo_real_tras_la_entrega(): void
    {
        [$ot, $solicitud] = $this->otConSolicitud(stock: 50, cantidad: 3, costo: 5000);

        app(\App\Services\OrdenTrabajo\AtencionInsumoOtService::class)
            ->entregar($solicitud, $this->almacenista());

        $costeo = app(CosteoOtService::class)->calcular($ot->fresh());
        $this->assertEqualsWithDelta(15000, $costeo['repuestos'], 0.01); // 3 uds. × $5.000 reales
    }

    public function test_el_tecnico_no_puede_abrir_la_pantalla(): void
    {
        Volt::actingAs($this->tecnicoUser())
            ->test('inventario.solicitudes-ot')
            ->assertForbidden();
    }

    public function test_bodega_es_de_un_solo_paso_sin_aprobacion(): void
    {
        // Ya no existe el paso "aprobar" (Phase 11 / D2).
        $this->assertFalse(method_exists(\App\Services\OrdenTrabajo\AtencionInsumoOtService::class, 'aprobar'));

        [$ot, $solicitud, $item] = $this->otConSolicitud(stock: 20, cantidad: 3);
        $this->assertSame('pendiente', $solicitud->estado);

        // Se entrega directo desde pendiente, sin estado intermedio.
        Volt::actingAs($this->almacenista())
            ->test('inventario.solicitudes-ot')
            ->call('entregar', $solicitud->id)
            ->assertHasNoErrors();

        $this->assertSame('entregada', $solicitud->fresh()->estado);
        $this->assertEquals(17, (float) $item->fresh()->stock_actual);
    }
}
