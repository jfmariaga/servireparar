<?php

namespace Tests\Unit\Services\Cotizaciones;

use App\Models\SolicitudDespacho;
use App\Services\Cotizaciones\OtifService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cálculo del KPI OTIF (spec: planilla "Orden perfecta" del usuario):
 * %OnTime y %InFull se calculan por separado, y %OTIF es el PRODUCTO de las
 * dos tasas — no un AND por pedido.
 */
class OtifServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calcula_on_time_in_full_y_otif_como_producto_de_las_dos_tasas(): void
    {
        // Reproduce el ejemplo de la planilla: 6 de 10 a tiempo, 8 de 10 completos.
        for ($i = 0; $i < 6; $i++) {
            SolicitudDespacho::factory()->create([
                'estado' => 'entregada',
                'fecha_promesa_entrega' => '2026-07-25',
                'entregada_en' => '2026-07-25 10:00:00',
                'entrega_completa' => true,
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            SolicitudDespacho::factory()->create([
                'estado' => 'entregada',
                'fecha_promesa_entrega' => '2026-07-25',
                'entregada_en' => '2026-07-28 10:00:00', // 3 días tarde
                'entrega_completa' => true,
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            SolicitudDespacho::factory()->create([
                'estado' => 'entregada',
                'fecha_promesa_entrega' => '2026-07-25',
                'entregada_en' => '2026-07-26 10:00:00', // 1 día tarde
                'entrega_completa' => false,
            ]);
        }

        $resumen = app(OtifService::class)->calcular();

        $this->assertSame(10, $resumen['total']);
        $this->assertSame(6, $resumen['on_time']);
        $this->assertSame(8, $resumen['in_full']);
        $this->assertSame(60.0, $resumen['pct_on_time']);
        $this->assertSame(80.0, $resumen['pct_in_full']);

        // Producto, no AND: 60% * 80% = 48%, NO el 60% que daría un AND por pedido.
        $this->assertSame(48.0, $resumen['pct_otif']);
        $this->assertNotEquals(60.0, $resumen['pct_otif']);
    }

    public function test_entrega_en_la_fecha_prometida_o_antes_cuenta_como_a_tiempo(): void
    {
        $aTiempo = SolicitudDespacho::factory()->create([
            'estado' => 'entregada',
            'fecha_promesa_entrega' => '2026-07-25',
            'entregada_en' => '2026-07-25 08:00:00',
            'entrega_completa' => true,
        ]);
        $temprano = SolicitudDespacho::factory()->create([
            'estado' => 'entregada',
            'fecha_promesa_entrega' => '2026-07-25',
            'entregada_en' => '2026-07-24 08:00:00',
            'entrega_completa' => true,
        ]);
        $tarde = SolicitudDespacho::factory()->create([
            'estado' => 'entregada',
            'fecha_promesa_entrega' => '2026-07-25',
            'entregada_en' => '2026-07-26 08:00:00',
            'entrega_completa' => true,
        ]);

        $this->assertTrue($aTiempo->entregaATiempo());
        $this->assertTrue($temprano->entregaATiempo());
        $this->assertFalse($tarde->entregaATiempo());
    }

    /**
     * @dataProvider nivelesProvider
     */
    public function test_los_umbrales_de_nivel_son_75_inclusive_y_85_inclusive(float $pctOtif, string $nivelEsperado): void
    {
        // on_time=1, total=1 -> pct_on_time=100; variamos in_full con 1/1 o fracciones vía varias solicitudes
        // para alcanzar el % exacto deseado: pct_otif = pct_on_time * pct_in_full / 100, con on_time=100% fijo.
        $totalInFull = (int) round($pctOtif);
        $totalFaltante = 100 - $totalInFull;

        for ($i = 0; $i < $totalInFull; $i++) {
            SolicitudDespacho::factory()->create([
                'estado' => 'entregada',
                'fecha_promesa_entrega' => '2026-07-25',
                'entregada_en' => '2026-07-25 08:00:00',
                'entrega_completa' => true,
            ]);
        }
        for ($i = 0; $i < $totalFaltante; $i++) {
            SolicitudDespacho::factory()->create([
                'estado' => 'entregada',
                'fecha_promesa_entrega' => '2026-07-25',
                'entregada_en' => '2026-07-25 08:00:00',
                'entrega_completa' => false,
            ]);
        }

        $resumen = app(OtifService::class)->calcular();

        $this->assertSame($nivelEsperado, $resumen['nivel']);
    }

    public static function nivelesProvider(): array
    {
        return [
            '74% -> plan de choque' => [74.0, 'plan_choque'],
            '75% -> plan corto plazo (límite inferior inclusive)' => [75.0, 'plan_corto_plazo'],
            '85% -> plan corto plazo (límite superior inclusive)' => [85.0, 'plan_corto_plazo'],
            '86% -> mantener y mejorar' => [86.0, 'mantener_mejorar'],
        ];
    }

    public function test_sin_despachos_entregados_retorna_null_sin_division_por_cero(): void
    {
        $resumen = app(OtifService::class)->calcular();

        $this->assertSame(0, $resumen['total']);
        $this->assertNull($resumen['pct_on_time']);
        $this->assertNull($resumen['pct_in_full']);
        $this->assertNull($resumen['pct_otif']);
        $this->assertNull($resumen['nivel']);
    }

    public function test_despachos_sin_fecha_prometida_o_sin_entregar_no_cuentan(): void
    {
        SolicitudDespacho::factory()->create([
            'estado' => 'entregada',
            'fecha_promesa_entrega' => null,
            'entregada_en' => now(),
            'entrega_completa' => true,
        ]);

        SolicitudDespacho::factory()->create([
            'estado' => 'remisionada',
            'fecha_promesa_entrega' => '2026-07-25',
            'entregada_en' => null,
            'entrega_completa' => null,
        ]);

        $resumen = app(OtifService::class)->calcular();

        $this->assertSame(0, $resumen['total']);
    }
}
