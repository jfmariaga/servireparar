<?php

namespace Tests\Feature\Inventario;

use App\Models\AjusteAuditoria;
use App\Models\Inventario;
use App\Models\User;
use App\Services\Inventario\MovimientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditoriaAjusteTest extends TestCase
{
    use RefreshDatabase;

    public function test_ajuste_pendiente_no_modifica_el_stock_actual(): void
    {
        $item = Inventario::factory()->create(['stock_actual' => 100]);

        AjusteAuditoria::factory()->create([
            'inventario_id' => $item->id,
            'stock_sistema' => 100,
            'stock_fisico' => 92,
            'estado' => 'pendiente',
        ]);

        $this->assertEquals(100, $item->fresh()->stock_actual);
    }

    public function test_aprobar_el_ajuste_aplica_el_stock_fisico_y_registra_quien_aprobo(): void
    {
        $item = Inventario::factory()->create(['stock_actual' => 100]);
        $administrador = User::factory()->create();

        $ajuste = AjusteAuditoria::factory()->create([
            'inventario_id' => $item->id,
            'stock_sistema' => 100,
            'stock_fisico' => 92,
            'estado' => 'pendiente',
        ]);

        (new MovimientoService())->aplicarAjusteAprobado($ajuste, $administrador);

        $this->assertEquals(92, $item->fresh()->stock_actual);
        $this->assertSame('aprobado', $ajuste->fresh()->estado);
        $this->assertSame($administrador->id, $ajuste->fresh()->aprobado_por);
        $this->assertNotNull($ajuste->fresh()->resuelto_en);
    }

    public function test_rechazar_el_ajuste_no_modifica_el_stock(): void
    {
        $item = Inventario::factory()->create(['stock_actual' => 100]);
        $administrador = User::factory()->create();

        $ajuste = AjusteAuditoria::factory()->create([
            'inventario_id' => $item->id,
            'stock_sistema' => 100,
            'stock_fisico' => 92,
            'estado' => 'pendiente',
        ]);

        $ajuste->update([
            'estado' => 'rechazado',
            'aprobado_por' => $administrador->id,
            'resuelto_en' => now(),
        ]);

        $this->assertEquals(100, $item->fresh()->stock_actual);
        $this->assertSame('rechazado', $ajuste->fresh()->estado);
    }

    public function test_ajuste_por_faltante_consume_lotes_por_fifo(): void
    {
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 0, 'costo_unitario' => null]);
        $administrador = User::factory()->create();
        $usuario = User::factory()->create();
        $servicio = new MovimientoService();

        $lote = $servicio->entrada($item, 10, $usuario, costoUnitario: 1000);

        $ajuste = AjusteAuditoria::factory()->create([
            'inventario_id' => $item->id,
            'stock_sistema' => 10,
            'stock_fisico' => 7, // faltaron 3 unidades
            'estado' => 'pendiente',
        ]);

        $servicio->aplicarAjusteAprobado($ajuste, $administrador);

        $this->assertEquals(7, $item->fresh()->stock_actual);
        $this->assertEquals(7, $lote->fresh()->cantidad_disponible);
        $this->assertEquals(7000, $item->fresh()->valorTotal());
    }

    public function test_ajuste_por_sobrante_crea_un_lote_nuevo_al_costo_de_referencia(): void
    {
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 10, 'costo_unitario' => 1500]);
        $administrador = User::factory()->create();

        $ajuste = AjusteAuditoria::factory()->create([
            'inventario_id' => $item->id,
            'stock_sistema' => 10,
            'stock_fisico' => 13, // sobraron 3 unidades
            'estado' => 'pendiente',
        ]);

        (new MovimientoService())->aplicarAjusteAprobado($ajuste, $administrador);

        $this->assertEquals(13, $item->fresh()->stock_actual);
        $this->assertEquals(4500, $item->fresh()->valorTotal()); // 3 * 1500
    }
}
