<?php

namespace App\Console\Commands;

use App\Models\DetalleOt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula `dias_trabajados` de las tareas YA finalizadas antes del cambio a
 * costeo por horas (spec 004): la fórmula vieja contaba días calendario desde
 * `fecha_inicio` (mínimo 1 día); la nueva usa horas reales de reloj hasta
 * `fecha_fin` divididas entre la jornada laboral configurada. Cambia costeo
 * histórico ya reportado/aprobado — por eso pide confirmación salvo --force,
 * y soporta --dry-run para ver el impacto sin escribir nada.
 */
class RecalcularDiasTrabajadosPorHoras extends Command
{
    protected $signature = 'ot:recalcular-dias-trabajados
        {--dry-run : Solo muestra los cambios, no escribe nada}
        {--force : No pedir confirmación}';

    protected $description = 'Recalcula dias_trabajados (horas reales / jornada) en tareas ya finalizadas, con la fórmula vieja por días calendario';

    public function handle(): int
    {
        $horasJornada = (float) config('ot.horas_jornada_laboral', 8);

        $tareas = DetalleOt::query()
            ->where('estado_tarea', 'finalizada')
            ->whereNotNull('fecha_inicio')
            ->whereNotNull('fecha_fin')
            ->with('ordenTrabajo:id,numero_ot')
            ->orderBy('id')
            ->get();

        $cambios = $tareas->map(function (DetalleOt $tarea) use ($horasJornada) {
            $horas = $tarea->fecha_inicio->diffInMinutes($tarea->fecha_fin) / 60;
            $nuevo = round($horas / max($horasJornada, 0.01), 2);

            return [
                'tarea' => $tarea,
                'ot' => $tarea->ordenTrabajo?->numero_ot ?? '—',
                'antes' => (float) $tarea->dias_trabajados,
                'despues' => $nuevo,
            ];
        })->filter(fn (array $c) => $c['antes'] !== $c['despues'])->values();

        if ($cambios->isEmpty()) {
            $this->info('Nada que recalcular: ninguna tarea finalizada cambia con la fórmula por horas.');

            return self::SUCCESS;
        }

        $this->table(
            ['OT', 'Tarea #', 'Días antes', 'Días después'],
            $cambios->map(fn (array $c) => [$c['ot'], $c['tarea']->id, $c['antes'], $c['despues']]),
        );

        if ($this->option('dry-run')) {
            $this->comment("({$cambios->count()} tarea(s) cambiarían — modo dry-run, no se escribió nada)");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            "Esto cambia el costeo histórico de {$cambios->count()} tarea(s) ya finalizadas. ¿Continuar?"
        )) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($cambios) {
            foreach ($cambios as $c) {
                $c['tarea']->update(['dias_trabajados' => $c['despues']]);
            }
        });

        $this->info("Recalculadas {$cambios->count()} tarea(s).");

        return self::SUCCESS;
    }
}
