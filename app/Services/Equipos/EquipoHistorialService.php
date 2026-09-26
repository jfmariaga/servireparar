<?php

namespace App\Services\Equipos;

use App\Models\Equipo;
use App\Models\OrdenTrabajo;
use Illuminate\Support\Collection;

/**
 * Reconstruye el historial técnico de un equipo (spec 005, US2/FR-003) a
 * partir de sus Órdenes de Trabajo (spec 002): técnico responsable,
 * evidencias, variables técnicas y checklist técnico (US4/FR-007)
 * registrados en cada intervención. Todo por consulta directa (sin tabla
 * propia), con eager loading para evitar N+1.
 */
class EquipoHistorialService
{
    /**
     * @return Collection<int, OrdenTrabajo>
     */
    public function historial(Equipo $equipo): Collection
    {
        return $equipo->ordenesTrabajo()
            ->with([
                'estado:id,slug,nombre',
                'tareas.tecnico.usuario:id,name',
                'evidencias.subidaPor:id,name',
                'variablesTecnicas.registradoPor:id,name',
                'checklistTecnico',
            ])
            ->latest('created_at')
            ->get();
    }
}
