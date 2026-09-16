<?php

namespace Tests\Feature\Personal;

use App\Models\DetalleOt;
use App\Models\EstadoOt;
use App\Models\OrdenTrabajo;
use App\Models\Tecnico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec 004, US2 (FR-005): visibilidad de la carga actual (tareas activas) de
 * cada técnico al momento de asignar.
 */
class CargaTecnicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cuenta_tareas_pendientes_y_en_curso_de_ot_liberadas(): void
    {
        $tecnico = Tecnico::factory()->create();
        $ot = OrdenTrabajo::factory()->enEstado(EstadoOt::EN_CURSO)->create();

        DetalleOt::factory()->for($ot, 'ordenTrabajo')->create(['tecnico_id' => $tecnico->id, 'estado_tarea' => 'pendiente']);
        DetalleOt::factory()->for($ot, 'ordenTrabajo')->enCurso()->create(['tecnico_id' => $tecnico->id]);
        DetalleOt::factory()->for($ot, 'ordenTrabajo')->finalizada()->create(['tecnico_id' => $tecnico->id]);

        $this->assertSame(2, $tecnico->tareasActivasCount());
    }

    public function test_tecnico_sin_tareas_asignadas_tiene_carga_cero(): void
    {
        $tecnico = Tecnico::factory()->create();

        $this->assertSame(0, $tecnico->tareasActivasCount());
    }

    public function test_no_cuenta_tareas_de_ot_aun_en_revision(): void
    {
        $tecnico = Tecnico::factory()->create();
        $ot = OrdenTrabajo::factory()->create(); // estado por defecto: en_revision

        DetalleOt::factory()->for($ot, 'ordenTrabajo')->create(['tecnico_id' => $tecnico->id, 'estado_tarea' => 'pendiente']);

        $this->assertSame(0, $tecnico->tareasActivasCount());
    }

    public function test_no_cuenta_tareas_de_ot_entregada(): void
    {
        $tecnico = Tecnico::factory()->create();
        $ot = OrdenTrabajo::factory()->enEstado(EstadoOt::ENTREGADA)->create();

        DetalleOt::factory()->for($ot, 'ordenTrabajo')->enCurso()->create(['tecnico_id' => $tecnico->id]);

        $this->assertSame(0, $tecnico->tareasActivasCount());
    }
}
