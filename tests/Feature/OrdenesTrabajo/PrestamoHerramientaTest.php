<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Enums\RolPrioridad;
use App\Models\Inventario;
use App\Models\PrestamoHerramienta;
use App\Models\Tecnico;
use App\Models\User;
use App\Services\OrdenTrabajo\EstadoOtService;
use App\Services\OrdenTrabajo\PrestamoHerramientaService;
use App\Services\OrdenTrabajo\SalidaEquipoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 12 · Fase 12.5 — herramientas como préstamo por técnico (D15-D16 / H29):
 * el técnico solicita, el Almacenista entrega/rechaza y registra la devolución;
 * el préstamo no bloquea el ciclo de la OT; el Jefe de Taller no interviene.
 */
class PrestamoHerramientaTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    private function almacenista(): User
    {
        return $this->usuarioConRol(RolPrioridad::Almacenista->value);
    }

    private function herramienta(string $estado = 'disponible'): Inventario
    {
        return Inventario::factory()->herramienta()->create(['estado_herramienta' => $estado, 'nombre' => 'Torquímetro']);
    }

    public function test_el_tecnico_solicita_y_el_almacenista_entrega(): void
    {
        $tec = Tecnico::factory()->conSueldo()->create();
        $tool = $this->herramienta();

        $prestamo = app(PrestamoHerramientaService::class)->solicitar($tec, $tool);
        $this->assertSame('solicitada', $prestamo->estado);

        app(PrestamoHerramientaService::class)->entregar($prestamo->fresh(), $this->almacenista());

        $prestamo->refresh();
        $this->assertSame('entregada', $prestamo->estado);
        $this->assertSame('en_uso', $tool->fresh()->estado_herramienta);
        $this->assertDatabaseHas('movimientos_inventario', ['inventario_id' => $tool->id, 'tipo_mov' => 'salida', 'origen' => 'prestamo']);
    }

    public function test_no_se_entrega_una_herramienta_no_disponible(): void
    {
        $tec = Tecnico::factory()->conSueldo()->create();
        $prestamo = app(PrestamoHerramientaService::class)->solicitar($tec, $this->herramienta('en_mantenimiento'));

        $this->expectException(ValidationException::class);
        app(PrestamoHerramientaService::class)->entregar($prestamo->fresh(), $this->almacenista());
    }

    public function test_el_almacenista_rechaza_con_motivo(): void
    {
        $tecUser = User::factory()->create();
        $tec = Tecnico::factory()->conSueldo()->create(['usuario_id' => $tecUser->id]);
        $prestamo = app(PrestamoHerramientaService::class)->solicitar($tec, $this->herramienta());

        app(PrestamoHerramientaService::class)->rechazar($prestamo->fresh(), $this->almacenista(), 'En uso por otro técnico');

        $prestamo->refresh();
        $this->assertSame('rechazada', $prestamo->estado);
        $this->assertSame('En uso por otro técnico', $prestamo->motivo_rechazo);
        $this->assertTrue($tecUser->fresh()->notifications()->where('data->titulo', 'Préstamo de herramienta rechazado')->exists());
    }

    public function test_la_devolucion_la_registra_el_almacenista(): void
    {
        $tec = Tecnico::factory()->conSueldo()->create();
        $tool = $this->herramienta();
        $svc = app(PrestamoHerramientaService::class);
        $prestamo = $svc->entregar($svc->solicitar($tec, $tool), $this->almacenista());

        $almacen = $this->almacenista();
        $svc->registrarDevolucion($prestamo->fresh(), $almacen, 'dañada');

        $prestamo->refresh();
        $this->assertSame('devuelta', $prestamo->estado);
        $this->assertSame('dañada', $prestamo->estado_devolucion);
        $this->assertSame($almacen->id, $prestamo->recibida_por);
        $this->assertSame('dañada', $tool->fresh()->estado_herramienta);
        $this->assertDatabaseHas('movimientos_inventario', ['inventario_id' => $tool->id, 'tipo_mov' => 'devolucion']);
    }

    public function test_la_ot_finaliza_y_se_entrega_con_prestamos_sin_devolver(): void
    {
        $ot = $this->crearOt(tareas: 1, datos: ['valor_proyecto' => 500_000]);
        $tecnico = $ot->tareas()->first()->tecnico;
        $svc = app(PrestamoHerramientaService::class);
        $svc->entregar($svc->solicitar($tecnico, $this->herramienta(), $ot->tareas()->first()), $this->almacenista());

        $this->finalizarTodasLasTareas($ot);
        $this->completarChecklist($ot);
        app(EstadoOtService::class)->recalcular($ot->fresh());

        $salida = app(SalidaEquipoService::class);
        $salida->solicitar($ot->fresh(['estado']), $this->jefeDeTaller());
        $salida->aprobar($ot->fresh(['estado']), $this->administrador());
        $salida->confirmarEntrega($ot->fresh(['estado']), $this->jefeDeTaller(), 'Firma');

        $this->assertSame('entregada', $ot->fresh()->estado->slug);
        $this->assertSame('entregada', PrestamoHerramienta::first()->estado, 'El préstamo no se toca al cerrar la OT.');
    }

    public function test_solicitar_avisa_a_bodega(): void
    {
        $almacen = $this->almacenista();
        $tec = Tecnico::factory()->conSueldo()->create();

        app(PrestamoHerramientaService::class)->solicitar($tec, $this->herramienta());

        $this->assertTrue($almacen->fresh()->notifications()->where('data->titulo', 'Préstamo de herramienta por atender')->exists());
    }

    public function test_solo_se_pide_en_prestamo_un_item_de_tipo_herramienta(): void
    {
        $tec = Tecnico::factory()->conSueldo()->create();
        $consumible = Inventario::factory()->create(['tipo' => 'consumible']);

        $this->expectException(ValidationException::class);
        app(PrestamoHerramientaService::class)->solicitar($tec, $consumible);
    }
}
