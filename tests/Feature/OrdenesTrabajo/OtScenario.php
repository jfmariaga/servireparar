<?php

namespace Tests\Feature\OrdenesTrabajo;

use App\Enums\RolPrioridad;
use App\Models\ChecklistOt;
use App\Models\Cliente;
use App\Models\DetalleOt;
use App\Models\OrdenTrabajo;
use App\Models\Tecnico;
use App\Models\User;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use Database\Seeders\EstadosOtSeeder;
use Database\Seeders\PrioridadesSeeder;
use Database\Seeders\RolesSeeder;

/**
 * Utilidades compartidas por los tests de OT (spec 002).
 */
trait OtScenario
{
    protected function seedOtCatalogos(): void
    {
        $this->seed([RolesSeeder::class, PrioridadesSeeder::class, EstadosOtSeeder::class]);
    }

    protected function usuarioConRol(string $rol): User
    {
        $user = User::factory()->create(['estado' => 'activo']);
        $user->assignRole($rol);

        return $user;
    }

    protected function jefeDeTaller(): User
    {
        return $this->usuarioConRol(RolPrioridad::JefeDeTaller->value);
    }

    protected function administrador(): User
    {
        return $this->usuarioConRol(RolPrioridad::Administrador->value);
    }

    protected function tecnicoUser(): User
    {
        return $this->usuarioConRol(RolPrioridad::Tecnico->value);
    }

    /**
     * Crea una OT con N tareas (una por técnico dado) vía el servicio real.
     *
     * @param  array<string, mixed>  $datos
     */
    protected function crearOt(array $datos = [], int $tareas = 1): OrdenTrabajo
    {
        $cliente = Cliente::factory()->create();
        $actor = $this->jefeDeTaller();

        $listaTareas = [];
        for ($i = 0; $i < $tareas; $i++) {
            $tecnico = Tecnico::factory()->conSueldo($datos['sueldo'] ?? 2_400_000)->create();
            $listaTareas[] = ['descripcion' => 'Tarea '.($i + 1), 'tecnico_id' => $tecnico->id];
        }

        return app(OrdenTrabajoService::class)->crear($actor, array_merge([
            'cliente_id' => $cliente->id,
            'prioridad_id' => \App\Models\Prioridad::where('nombre', 'Media')->value('id'),
            'descripcion' => 'OT de prueba',
            'tiempo_estimado_dias' => $datos['tiempo_estimado_dias'] ?? 5,
        ], $datos), $listaTareas);
    }

    protected function completarChecklist(OrdenTrabajo $ot, bool $cumple = true): void
    {
        ChecklistOt::factory()->for($ot, 'ordenTrabajo')->create(['cumple' => $cumple]);
    }

    protected function finalizarTodasLasTareas(OrdenTrabajo $ot, float $dias = 2): void
    {
        $ot->tareas()->each(function (DetalleOt $t) use ($dias) {
            $t->update([
                'estado_tarea' => 'finalizada',
                'fecha_inicio' => now()->subDays((int) ceil($dias)),
                'fecha_fin' => now(),
                'dias_trabajados' => $dias,
            ]);
        });
    }
}
