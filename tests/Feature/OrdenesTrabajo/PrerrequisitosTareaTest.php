<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Enums\RolPrioridad;
use App\Models\Cliente;
use App\Models\Prioridad;
use App\Models\Tecnico;
use App\Services\OrdenTrabajo\EstadoOtService;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Phase 12 · Fase 12.2 — prerrequisitos entre tareas de una misma OT (D10 / H25):
 * no se inicia una tarea sin finalizar sus prerrequisitos; sin ciclos; cancelar
 * un prerrequisito libera a sus dependientes.
 */
class PrerrequisitosTareaTest extends TestCase
{
    use OtScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedOtCatalogos();
    }

    /** @return array{0: \App\Models\OrdenTrabajo, 1: array<string, \App\Models\DetalleOt>} */
    private function crearOtConTareas(array $defs): array
    {
        $cliente = Cliente::factory()->create();
        $tareas = [];
        foreach ($defs as $uid => $def) {
            $tec = Tecnico::factory()->conSueldo(2_400_000)->create();
            $tareas[] = [
                'uid' => $uid,
                'descripcion' => $def['descripcion'] ?? $uid,
                'tecnico_id' => $tec->id,
                'prerrequisitos' => $def['prerrequisitos'] ?? [],
            ];
        }

        $ot = app(OrdenTrabajoService::class)->crear($this->jefeDeTaller(), [
            'cliente_id' => $cliente->id,
            'prioridad_id' => Prioridad::where('nombre', 'Media')->value('id'),
            'descripcion' => 'OT con prerrequisitos',
            'tiempo_estimado_dias' => 5,
        ], $tareas);

        $porDescripcion = $ot->tareas()->get()->keyBy('descripcion');

        return [$ot, $porDescripcion];
    }

    public function test_no_se_inicia_una_tarea_con_prerrequisito_pendiente(): void
    {
        [$ot, $t] = $this->crearOtConTareas([
            'a' => ['descripcion' => 'A'],
            'b' => ['descripcion' => 'B', 'prerrequisitos' => ['a']],
        ]);

        $tecnicoUserB = $t['B']->tecnico->usuario;
        $tecnicoUserB->assignRole(RolPrioridad::Tecnico->value);

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('planificar', app(EstadoOtService::class));

        Volt::actingAs($tecnicoUserB)
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot->fresh()])
            ->call('iniciarTarea', $t['B']->id, app(EstadoOtService::class));

        $this->assertSame('pendiente', $t['B']->fresh()->estado_tarea, 'B no debe iniciarse mientras A no esté finalizada.');
    }

    public function test_se_inicia_al_finalizar_el_prerrequisito(): void
    {
        [$ot, $t] = $this->crearOtConTareas([
            'a' => ['descripcion' => 'A'],
            'b' => ['descripcion' => 'B', 'prerrequisitos' => ['a']],
        ]);

        $t['A']->update(['estado_tarea' => 'finalizada', 'fecha_fin' => now(), 'dias_trabajados' => 1]);
        $tecnicoUserB = $t['B']->tecnico->usuario;
        $tecnicoUserB->assignRole(RolPrioridad::Tecnico->value);

        Volt::actingAs($this->jefeDeTaller())
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot])
            ->call('planificar', app(EstadoOtService::class));

        Volt::actingAs($tecnicoUserB)
            ->test('ordenes-trabajo.detalle', ['ordenTrabajo' => $ot->fresh()])
            ->call('iniciarTarea', $t['B']->id, app(EstadoOtService::class));

        $this->assertSame('en_curso', $t['B']->fresh()->estado_tarea);
    }

    public function test_rechaza_ciclo_en_creacion(): void
    {
        $this->expectException(ValidationException::class);

        $this->crearOtConTareas([
            'a' => ['descripcion' => 'A', 'prerrequisitos' => ['b']],
            'b' => ['descripcion' => 'B', 'prerrequisitos' => ['a']],
        ]);
    }

    public function test_rechaza_ciclo_al_editar_una_tarea(): void
    {
        [$ot, $t] = $this->crearOtConTareas([
            'a' => ['descripcion' => 'A'],
            'b' => ['descripcion' => 'B', 'prerrequisitos' => ['a']],
        ]);

        $this->expectException(ValidationException::class);

        // A pasaría a depender de B, cerrando el ciclo A→B→A.
        app(OrdenTrabajoService::class)->actualizarTarea($t['A']->fresh(), $this->jefeDeTaller(), [
            'prerrequisitos' => [$t['B']->id],
        ]);
    }

    public function test_multi_prerrequisito_retiene_hasta_finalizar_todos(): void
    {
        [$ot, $t] = $this->crearOtConTareas([
            'a' => ['descripcion' => 'A'],
            'b' => ['descripcion' => 'B'],
            'c' => ['descripcion' => 'C', 'prerrequisitos' => ['a', 'b']],
        ]);

        $this->assertCount(2, $t['C']->prerrequisitosPendientes());

        $t['A']->update(['estado_tarea' => 'finalizada', 'dias_trabajados' => 1]);
        $this->assertCount(1, $t['C']->fresh()->prerrequisitosPendientes());
    }

    public function test_cancelar_un_prerrequisito_libera_a_la_dependiente(): void
    {
        [$ot, $t] = $this->crearOtConTareas([
            'a' => ['descripcion' => 'A'],
            'b' => ['descripcion' => 'B'],
            'c' => ['descripcion' => 'C', 'prerrequisitos' => ['a', 'b']],
        ]);
        $t['A']->update(['estado_tarea' => 'finalizada', 'dias_trabajados' => 1]);

        app(OrdenTrabajoService::class)->cancelarTarea($t['B']->fresh(), $this->jefeDeTaller(), 'Ya no aplica');

        $c = $t['C']->fresh();
        $this->assertTrue($c->prerrequisitos()->pluck('detalle_ot.id')->doesntContain($t['B']->id), 'C ya no debe requerir B.');
        $this->assertCount(0, $c->prerrequisitosPendientes(), 'C queda libre (A finalizada, B ya no cuenta).');
        $this->assertTrue(
            $ot->fresh()->eventos->contains(fn ($e) => str_contains($e->descripcion, 'ya no depende de')),
            'Debe quedar traza de la liberación del vínculo.',
        );
    }

    public function test_una_tarea_bloqueada_por_prerrequisitos_no_impide_derivar_el_estado(): void
    {
        [$ot, $t] = $this->crearOtConTareas([
            'a' => ['descripcion' => 'A'],
            'b' => ['descripcion' => 'B', 'prerrequisitos' => ['a']],
        ]);

        app(EstadoOtService::class)->planificar($ot, $this->jefeDeTaller());
        $t['A']->update(['estado_tarea' => 'en_curso', 'fecha_inicio' => now()]);
        app(EstadoOtService::class)->recalcular($ot->fresh(), $this->jefeDeTaller());

        $this->assertSame('en_curso', $ot->fresh()->estado->slug);
    }
}
