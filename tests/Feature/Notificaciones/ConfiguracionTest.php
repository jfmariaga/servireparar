<?php

namespace Tests\Feature\Notificaciones;

use App\Enums\RolPrioridad;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\SolicitudDespacho;
use App\Services\Inventario\FirmaFisicaPendienteService;
use App\Services\OrdenTrabajo\VencimientoOtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\OrdenesTrabajo\OtScenario;
use Tests\TestCase;

/**
 * Umbrales editables en BD sin cambios de código (spec 008, T003-T005). El
 * valor en `config/*.php` sigue funcionando como respaldo cuando no hay fila
 * en `configuraciones` (instalaciones sin seed, o la clave todavía no existe).
 */
class ConfiguracionTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    public function test_obtener_usa_el_valor_por_defecto_si_no_existe_la_clave(): void
    {
        $this->assertSame('7', Configuracion::obtener('equipos.dias_antelacion_mantenimiento', '7'));
    }

    public function test_establecer_crea_o_actualiza_el_valor(): void
    {
        Configuracion::establecer('ot.dias_umbral_vencimiento', '5');
        $this->assertSame('5', Configuracion::obtener('ot.dias_umbral_vencimiento'));

        Configuracion::establecer('ot.dias_umbral_vencimiento', '9');
        $this->assertSame('9', Configuracion::obtener('ot.dias_umbral_vencimiento'));
        $this->assertSame(1, Configuracion::where('clave', 'ot.dias_umbral_vencimiento')->count());
    }

    public function test_vencimiento_de_ot_respeta_el_umbral_configurado_en_bd(): void
    {
        Configuracion::establecer('ot.dias_umbral_vencimiento', '0');
        $this->crearOt(['tiempo_estimado_dias' => 0.9], tareas: 1);

        // Con umbral 0 (en vez del default de config, 2), una OT de menos de 1 día
        // estimado todavía no está próxima a vencer.
        $this->assertSame(0, app(VencimientoOtService::class)->revisar());

        Configuracion::establecer('ot.dias_umbral_vencimiento', '2');
        $this->assertSame(1, app(VencimientoOtService::class)->revisar());
    }

    public function test_firma_fisica_pendiente_respeta_el_umbral_configurado_en_bd(): void
    {
        $this->usuarioConRol(RolPrioridad::Almacenista->value);
        $cliente = Cliente::factory()->create();
        SolicitudDespacho::factory()->create([
            'cliente_id' => $cliente->id,
            'estado' => 'despachada',
            'despachada_en' => Carbon::now()->subDays(2),
        ]);

        Configuracion::establecer('despachos.dias_alerta_firma_pendiente', '5');
        $this->assertSame(0, app(FirmaFisicaPendienteService::class)->revisar(), 'Con umbral 5 días, 2 días de espera no alerta.');

        Configuracion::establecer('despachos.dias_alerta_firma_pendiente', '1');
        $this->assertSame(1, app(FirmaFisicaPendienteService::class)->revisar());
    }
}
