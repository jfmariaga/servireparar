<?php

namespace Tests\Feature\Inventario;

use App\Events\StockBajo;
use App\Exceptions\StockInsuficienteException;
use App\Models\Inventario;
use App\Models\User;
use App\Services\Inventario\MovimientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MovimientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_entrada_incrementa_el_stock_actual(): void
    {
        $item = Inventario::factory()->create(['stock_actual' => 10]);
        $usuario = User::factory()->create();

        (new MovimientoService())->entrada($item, 25, $usuario);

        $this->assertEquals(35, $item->fresh()->stock_actual);
    }

    public function test_salida_de_consumible_descuenta_stock(): void
    {
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 50, 'stock_minimo' => 5]);
        $usuario = User::factory()->create();

        (new MovimientoService())->salida($item, 20, $usuario, origen: 'manual');

        $this->assertEquals(30, $item->fresh()->stock_actual);
    }

    public function test_salida_bloquea_si_stock_es_insuficiente(): void
    {
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 5]);
        $usuario = User::factory()->create();

        try {
            (new MovimientoService())->salida($item, 10, $usuario, origen: 'manual');
            $this->fail('Se esperaba StockInsuficienteException.');
        } catch (StockInsuficienteException) {
            // esperado
        }

        $this->assertEquals(5, $item->fresh()->stock_actual);
    }

    public function test_salida_de_herramienta_la_marca_en_uso(): void
    {
        $item = Inventario::factory()->herramienta()->create();
        $usuario = User::factory()->create();

        (new MovimientoService())->salida($item, 1, $usuario, origen: 'manual');

        $this->assertSame('en_uso', $item->fresh()->estado_herramienta);
    }

    public function test_devolucion_deja_estado_final_explicito(): void
    {
        $item = Inventario::factory()->herramienta()->create(['estado_herramienta' => 'en_uso']);
        $usuario = User::factory()->create();

        (new MovimientoService())->devolucion($item, $usuario, 'disponible');
        $this->assertSame('disponible', $item->fresh()->estado_herramienta);

        (new MovimientoService())->devolucion($item, $usuario, 'dañada', motivo: 'Cable roto');
        $this->assertSame('dañada', $item->fresh()->estado_herramienta);
    }

    public function test_dispara_evento_stock_bajo_al_cruzar_el_minimo(): void
    {
        Event::fake();

        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 12, 'stock_minimo' => 10]);
        $usuario = User::factory()->create();

        (new MovimientoService())->salida($item, 5, $usuario, origen: 'manual');

        Event::assertDispatched(StockBajo::class, fn ($event) => $event->inventario->id === $item->id);
    }

    public function test_no_dispara_evento_si_el_stock_sigue_por_encima_del_minimo(): void
    {
        Event::fake();

        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 100, 'stock_minimo' => 10]);
        $usuario = User::factory()->create();

        (new MovimientoService())->salida($item, 5, $usuario, origen: 'manual');

        Event::assertNotDispatched(StockBajo::class);
    }

    public function test_entrada_con_costo_reemplaza_el_costo_del_item_por_el_de_esta_entrada(): void
    {
        $item = Inventario::factory()->create(['stock_actual' => 10, 'costo_unitario' => 1000]);
        $usuario = User::factory()->create();

        (new MovimientoService())->entrada($item, 10, $usuario, costoUnitario: 2000);

        $this->assertEquals(2000, $item->fresh()->costo_unitario);
        $this->assertEquals(20, $item->fresh()->stock_actual);
    }

    public function test_entrada_sin_costo_anterior_usa_el_costo_de_la_entrada(): void
    {
        $item = Inventario::factory()->create(['stock_actual' => 0, 'costo_unitario' => null]);
        $usuario = User::factory()->create();

        (new MovimientoService())->entrada($item, 5, $usuario, costoUnitario: 3000);

        $this->assertEquals(3000, $item->fresh()->costo_unitario);
    }

    public function test_entrada_sin_informar_costo_no_modifica_el_costo_vigente(): void
    {
        $item = Inventario::factory()->create(['stock_actual' => 10, 'costo_unitario' => 1000]);
        $usuario = User::factory()->create();

        (new MovimientoService())->entrada($item, 5, $usuario);

        $this->assertEquals(1000, $item->fresh()->costo_unitario);
    }

    public function test_movimiento_de_entrada_guarda_el_costo_de_esa_entrada(): void
    {
        $item = Inventario::factory()->create(['stock_actual' => 10, 'costo_unitario' => 1000]);
        $usuario = User::factory()->create();

        $movimiento = (new MovimientoService())->entrada($item, 10, $usuario, costoUnitario: 2000);

        $this->assertEquals(2000, $movimiento->costo_unitario);
    }

    public function test_salida_sin_lotes_registrados_usa_el_costo_de_referencia_del_item(): void
    {
        // Ítem sin movimientos de entrada propios (ej. stock cargado directamente) —
        // no hay lote de dónde consumir, así que cae al costo de referencia del maestro.
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 50, 'stock_minimo' => 5, 'costo_unitario' => 1500]);
        $usuario = User::factory()->create();

        $movimiento = (new MovimientoService())->salida($item, 5, $usuario, origen: 'manual');

        $this->assertEquals(1500, $movimiento->costo_unitario);
    }

    public function test_salida_consume_un_solo_lote_y_registra_su_costo_exacto(): void
    {
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 0, 'stock_minimo' => 0, 'costo_unitario' => null]);
        $usuario = User::factory()->create();
        $servicio = new MovimientoService();

        $lote = $servicio->entrada($item, 10, $usuario, costoUnitario: 1000);

        $salida = $servicio->salida($item, 4, $usuario, origen: 'manual');

        $this->assertEquals(1000, $salida->costo_unitario);
        $this->assertEquals(6, $lote->fresh()->cantidad_disponible);
        $this->assertEquals(6, $item->fresh()->stock_actual);
    }

    public function test_salida_que_cruza_dos_lotes_consume_fifo_y_promedia_solo_lo_consumido(): void
    {
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 0, 'stock_minimo' => 0, 'costo_unitario' => null]);
        $usuario = User::factory()->create();
        $servicio = new MovimientoService();

        $loteViejo = $servicio->entrada($item, 10, $usuario, costoUnitario: 1000);
        $loteNuevo = $servicio->entrada($item, 10, $usuario, costoUnitario: 2000);

        $salida = $servicio->salida($item, 15, $usuario, origen: 'manual');

        // Consume las 10 unidades del lote viejo ($1.000) y 5 del nuevo ($2.000):
        // (10*1000 + 5*2000) / 15 = 1333.33 — el costo real de lo que salió, no un
        // promedio de todo el inventario ni el precio de la compra más reciente.
        $this->assertEquals(1333.33, $salida->costo_unitario);
        $this->assertEquals(0, $loteViejo->fresh()->cantidad_disponible);
        $this->assertEquals(5, $loteNuevo->fresh()->cantidad_disponible);
        $this->assertEquals(5, $item->fresh()->stock_actual);
    }

    public function test_valor_total_del_item_suma_los_lotes_reales_no_el_costo_del_maestro(): void
    {
        $item = Inventario::factory()->create(['tipo' => 'consumible', 'stock_actual' => 0, 'stock_minimo' => 0, 'costo_unitario' => null]);
        $usuario = User::factory()->create();
        $servicio = new MovimientoService();

        $servicio->entrada($item, 10, $usuario, costoUnitario: 1000);
        $servicio->entrada($item, 10, $usuario, costoUnitario: 2000);

        // 10*1000 + 10*2000 = 30.000 — no 20*2000 = 40.000, que es lo que daría usar
        // solo el costo de la última entrada para todo el stock.
        $this->assertEquals(30000, $item->fresh()->valorTotal());
    }
}
