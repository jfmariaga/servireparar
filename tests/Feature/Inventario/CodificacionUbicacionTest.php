<?php

namespace Tests\Feature\Inventario;

use App\Models\CategoriaInventario;
use App\Models\Inventario;
use App\Services\Inventario\CodigoInternoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodificacionUbicacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_genera_codigo_con_prefijo_de_la_categoria_y_consecutivo(): void
    {
        $categoria = CategoriaInventario::factory()->create(['prefijo_codigo' => 'REP-']);

        $primero = (new CodigoInternoService())->generar($categoria);
        $this->assertSame('REP-00001', $primero);

        Inventario::factory()->create(['codigo' => $primero, 'categoria_id' => $categoria->id]);

        $segundo = (new CodigoInternoService())->generar($categoria);
        $this->assertSame('REP-00002', $segundo);
    }

    public function test_el_consecutivo_es_independiente_por_prefijo(): void
    {
        $repuestos = CategoriaInventario::factory()->create(['nombre' => 'Repuestos', 'prefijo_codigo' => 'REP-']);
        $herramientas = CategoriaInventario::factory()->create(['nombre' => 'Herramientas', 'prefijo_codigo' => 'HER-']);

        Inventario::factory()->create(['codigo' => 'REP-00001', 'categoria_id' => $repuestos->id]);

        $codigoHerramienta = (new CodigoInternoService())->generar($herramientas);
        $this->assertSame('HER-00001', $codigoHerramienta);
    }
}
