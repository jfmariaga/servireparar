<?php

namespace App\Services\Reportes;

use App\Models\Cliente;
use App\Models\DetalleOt;
use App\Models\EstadoOt;
use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use App\Models\PrestamoHerramienta;
use App\Models\Tecnico;
use App\Services\Personal\DesempenoTecnicoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Spec 007, US2 (FR-002/FR-003/FR-004): OT abiertas/cerradas/vencidas,
 * cumplimiento de tiempos y productividad del equipo — todo calculado por
 * consulta directa (sin caché), reutilizando `DesempenoTecnicoService`
 * (spec 004) para no duplicar el cálculo de desempeño por técnico.
 */
class IndicadoresAgregadosService
{
    public function __construct(private readonly DesempenoTecnicoService $desempeno = new DesempenoTecnicoService()) {}

    /**
     * OT abiertas, cerradas, vencidas y próximas a vencer. Usa el mismo umbral
     * y fórmula de límite (`created_at + tiempo_estimado_dias`) que
     * `VencimientoOtService`, para que ambos indicadores coincidan.
     *
     * @return array{abiertas: int, cerradas: int, vencidas: int, proximas_a_vencer: int}
     */
    public function otResumen(): array
    {
        $clasificadas = $this->clasificarAbiertas();

        return [
            'abiertas' => $clasificadas['abiertas']->count(),
            'cerradas' => $this->otCerradas()->count(),
            'vencidas' => $clasificadas['vencidas']->count(),
            'proximas_a_vencer' => $clasificadas['proximas_a_vencer']->count(),
        ];
    }

    /**
     * OT de una categoría del resumen anterior, para el modal de detalle del
     * dashboard (drill-down de las 4 tarjetas). Reutiliza `clasificarAbiertas()`
     * para que la lista mostrada nunca se desalinee del conteo de `otResumen()`.
     *
     * @return Collection<int, OrdenTrabajo>
     */
    public function otEnCategoria(string $categoria): Collection
    {
        if ($categoria === 'cerradas') {
            return $this->otCerradas()->latest('id')->get();
        }

        return $this->clasificarAbiertas()[$categoria] ?? collect();
    }

    /**
     * Clasifica las OT abiertas en abiertas/vencidas/próximas a vencer,
     * comparando `created_at + tiempo_estimado_dias` contra hoy (mismo umbral
     * que `VencimientoOtService`). Base compartida de `otResumen()` y
     * `otEnCategoria()` para que conteo y listado nunca diverjan.
     *
     * @return array{abiertas: Collection<int, OrdenTrabajo>, vencidas: Collection<int, OrdenTrabajo>, proximas_a_vencer: Collection<int, OrdenTrabajo>}
     */
    private function clasificarAbiertas(): array
    {
        $umbral = (int) config('ot.dias_umbral_vencimiento', 2);
        $hoy = Carbon::today();

        $abiertas = OrdenTrabajo::query()
            ->whereHas('estado', fn ($q) => $q->whereNotIn('slug', [EstadoOt::FINALIZADA, EstadoOt::ENTREGADA, EstadoOt::CANCELADA]))
            ->with(['cliente:id,nombre', 'estado:id,slug,nombre'])
            ->latest('id')
            ->get();

        $vencidas = collect();
        $proximasAVencer = collect();

        foreach ($abiertas->whereNotNull('tiempo_estimado_dias') as $ot) {
            $limite = Carbon::parse($ot->created_at)->addDays((float) $ot->tiempo_estimado_dias)->startOfDay();
            $diasParaLimite = ($limite->getTimestamp() - $hoy->getTimestamp()) / 86400;

            if ($diasParaLimite < 0) {
                $vencidas->push($ot);
            } elseif ($diasParaLimite <= $umbral) {
                $proximasAVencer->push($ot);
            }
        }

        return ['abiertas' => $abiertas, 'vencidas' => $vencidas, 'proximas_a_vencer' => $proximasAVencer];
    }

    private function otCerradas(): \Illuminate\Database\Eloquent\Builder
    {
        return OrdenTrabajo::query()
            ->whereHas('estado', fn ($q) => $q->whereIn('slug', [EstadoOt::FINALIZADA, EstadoOt::ENTREGADA]))
            ->with(['cliente:id,nombre', 'estado:id,slug,nombre']);
    }

    /**
     * % de OT finalizadas/entregadas cuyo total de días trabajados no superó
     * el tiempo estimado (`OrdenTrabajo::desviacionDias() <= 0`). `null` si no
     * hay OT cerradas con tiempo estimado para comparar.
     */
    public function cumplimientoTiempos(): ?float
    {
        $cerradas = OrdenTrabajo::query()
            ->whereHas('estado', fn ($q) => $q->whereIn('slug', [EstadoOt::FINALIZADA, EstadoOt::ENTREGADA]))
            ->whereNotNull('tiempo_estimado_dias')
            ->with('tareas')
            ->get();

        if ($cerradas->isEmpty()) {
            return null;
        }

        $dentroDelEstimado = $cerradas->filter(fn (OrdenTrabajo $ot) => ($ot->desviacionDias() ?? 0) <= 0)->count();

        return round($dentroDelEstimado / $cerradas->count() * 100, 1);
    }

    /**
     * Productividad del equipo: agregado de `DesempenoTecnicoService::resumenPorTecnico()`
     * sobre el rango de fechas dado.
     *
     * @return array{tecnicos_activos: int, tareas_finalizadas_total: int, cumplimiento_promedio_pct: ?float}
     */
    public function productividadEquipo(?Carbon $desde = null, ?Carbon $hasta = null): array
    {
        $porTecnico = $this->desempeno->resumenPorTecnico($desde, $hasta);
        $conCumplimiento = $porTecnico->pluck('cumplimiento_pct')->filter(fn ($v) => $v !== null);

        return [
            'tecnicos_activos' => $porTecnico->count(),
            'tareas_finalizadas_total' => (int) $porTecnico->sum('tareas_finalizadas'),
            'cumplimiento_promedio_pct' => $conCumplimiento->isNotEmpty() ? round($conCumplimiento->avg(), 1) : null,
        ];
    }

    /**
     * OT del "piso" (ya liberadas y aún no cerradas) para las tarjetas del
     * dashboard en tiempo real: una tarjeta por OT, no por tarea — mismo
     * criterio de "activa" que la vista del técnico (ni en Planificación ni
     * en un estado terminal), expresado directamente sobre `estado_id` de la
     * OT (`pendiente` = por iniciar, `en_curso` = en curso).
     *
     * Trae lo necesario para las métricas de cada tarjeta: el conteo de
     * tareas de la OT vía `withCount` (avance), y las tareas activas con
     * `tecnico`/`prerrequisitos`/`solicitudesInsumo` para poder calcular
     * quién trabaja en ella y si está bloqueada, sin N+1.
     *
     * @return Collection<int, OrdenTrabajo>
     */
    public function otsDelPiso(): Collection
    {
        return OrdenTrabajo::query()
            ->whereHas('estado', fn ($q) => $q->whereIn('slug', [EstadoOt::PENDIENTE, EstadoOt::EN_CURSO]))
            ->with(['cliente:id,nombre', 'estado:id,slug,nombre'])
            ->withCount([
                'tareas as tareas_total',
                'tareas as tareas_finalizadas_count' => fn ($q) => $q->where('estado_tarea', 'finalizada'),
            ])
            ->with(['tareas' => fn ($q) => $q
                ->whereIn('estado_tarea', ['pendiente', 'en_curso'])
                ->with(['tecnico.usuario:id,name', 'prerrequisitos', 'solicitudesInsumo']),
            ])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Concurrencia de tareas en curso por hora, para la gráfica de tendencia
     * de la vista "piso" del dashboard (spec 007 extendida): para cada bucket
     * horario de las últimas `$horas`, cuenta las tareas cuyo intervalo
     * [fecha_inicio, fecha_fin] se solapa con ese bucket, separadas por
     * `tipo_servicio` de la OT. No requiere tabla de snapshots: se reconstruye
     * a partir de los timestamps ya guardados en `detalle_ot`.
     *
     * @return array<int, array{hora: string, taller: int, domicilio: int}>
     */
    public function tendenciaOtEnCurso(int $horas = 24): array
    {
        $ahora = Carbon::now();
        $desde = $ahora->copy()->subHours($horas - 1)->startOfHour();

        $tareas = DetalleOt::query()
            ->whereNotNull('fecha_inicio')
            ->where('fecha_inicio', '<=', $ahora)
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $desde))
            ->with('ordenTrabajo:id,tipo_servicio')
            ->get(['id', 'ot_id', 'fecha_inicio', 'fecha_fin']);

        $buckets = [];

        for ($i = 0; $i < $horas; $i++) {
            $inicioBucket = $desde->copy()->addHours($i);
            $finBucket = $inicioBucket->copy()->addHour();

            $enCurso = $tareas->filter(function (DetalleOt $t) use ($inicioBucket, $finBucket) {
                // Sin fecha_fin: la tarea sigue en curso, se trata como "sin límite"
                // en vez de fijarla a `now()` (que excluiría el bucket actual).
                $fin = $t->fecha_fin ?? $finBucket->copy()->addCentury();

                return $t->fecha_inicio->lt($finBucket) && $fin->gt($inicioBucket);
            });

            $buckets[] = [
                'hora' => $inicioBucket->format('H:i'),
                'taller' => $enCurso->filter(fn (DetalleOt $t) => ($t->ordenTrabajo?->tipo_servicio ?? 'taller') === 'taller')->count(),
                'domicilio' => $enCurso->filter(fn (DetalleOt $t) => $t->ordenTrabajo?->tipo_servicio === 'domicilio')->count(),
            ];
        }

        return $buckets;
    }

    /**
     * Herramientas dañadas o en mantenimiento, pendientes de gestión (spec 007,
     * Acceptance Scenario 2 del Administrador).
     *
     * @return Collection<int, Inventario>
     */
    public function herramientasPendientesGestion(): Collection
    {
        return Inventario::query()
            ->where('tipo', 'herramienta')
            ->whereIn('estado_herramienta', ['dañada', 'en_mantenimiento'])
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Préstamos de herramienta entregados y aún sin devolver, más antiguos
     * primero, para detectar los que llevan más tiempo fuera.
     *
     * @return Collection<int, PrestamoHerramienta>
     */
    public function prestamosSinDevolver(): Collection
    {
        return PrestamoHerramienta::query()
            ->sinDevolver()
            ->with(['inventario:id,nombre,codigo', 'tecnico.usuario:id,name'])
            ->orderBy('solicitada_en')
            ->get();
    }

    /**
     * Consumibles cuyo stock actual está por debajo del mínimo, reutilizando
     * `Inventario::stockBajoMinimo()` para no duplicar la regla.
     *
     * @return Collection<int, Inventario>
     */
    public function stockBajo(): Collection
    {
        return Inventario::query()
            ->where('tipo', 'consumible')
            ->with('unidadMedida:id,abreviatura')
            ->get()
            ->filter(fn (Inventario $i) => $i->stockBajoMinimo())
            ->values();
    }

    /**
     * Técnicos activos libres vs. ocupados ahora mismo, con el mismo criterio
     * de "tarea activa" que `DesempenoTecnicoService::tareasActivas()` (OT
     * liberada y no en estado terminal), calculado en bloque para evitar N+1.
     * Incluye el listado nominal de cada grupo para el drill-down del panel.
     *
     * @return array{libres: int, ocupados: int, libresLista: Collection<int, array{tecnico_id: int, nombre: string}>, ocupadosLista: Collection<int, array{tecnico_id: int, nombre: string, tareas_activas: int}>}
     */
    public function tecnicosDisponibilidad(): array
    {
        $activos = Tecnico::disponibles()->with('usuario:id,name')->get();

        $tareasPorTecnico = DetalleOt::query()
            ->whereIn('estado_tarea', ['pendiente', 'en_curso'])
            ->whereNotNull('tecnico_id')
            ->with(['ordenTrabajo:id,estado_id', 'ordenTrabajo.estado:id,slug,es_terminal'])
            ->get()
            ->filter(fn (DetalleOt $t) => ! $t->ordenTrabajo?->estaEnEstado(EstadoOt::EN_REVISION)
                && ! optional($t->ordenTrabajo?->estado)->es_terminal)
            ->groupBy('tecnico_id');

        $ocupadosIds = $tareasPorTecnico->keys();

        $ocupadosLista = $activos->filter(fn (Tecnico $t) => $ocupadosIds->contains($t->id))
            ->map(fn (Tecnico $t) => [
                'tecnico_id' => $t->id,
                'nombre' => $t->usuario?->name ?? 'Técnico #'.$t->id,
                'tareas_activas' => $tareasPorTecnico->get($t->id)?->count() ?? 0,
            ])->values();

        $libresLista = $activos->reject(fn (Tecnico $t) => $ocupadosIds->contains($t->id))
            ->map(fn (Tecnico $t) => ['tecnico_id' => $t->id, 'nombre' => $t->usuario?->name ?? 'Técnico #'.$t->id])
            ->values();

        return [
            'libres' => $libresLista->count(),
            'ocupados' => $ocupadosLista->count(),
            'libresLista' => $libresLista,
            'ocupadosLista' => $ocupadosLista,
        ];
    }

    /**
     * Tareas en curso sin avance reciente (sin `fecha_fin` y sin actualizarse
     * en `$diasUmbral` días): señal de posible trabajo estancado, distinta de
     * "vencida" (que mide contra el plazo estimado, no la actividad real).
     *
     * @return Collection<int, DetalleOt>
     */
    public function otEstancadas(int $diasUmbral = 3): Collection
    {
        return DetalleOt::query()
            ->where('estado_tarea', 'en_curso')
            ->where('updated_at', '<', Carbon::now()->subDays($diasUmbral))
            ->with(['ordenTrabajo:id,numero_ot,cliente_id', 'ordenTrabajo.cliente:id,nombre', 'tecnico.usuario:id,name'])
            ->orderBy('updated_at')
            ->get();
    }

    /**
     * Clientes con más OT creadas en el rango (o histórico si no se filtra).
     *
     * @return Collection<int, array{cliente_id: int, nombre: string, ot_count: int}>
     */
    public function clientesTop(int $limite = 5, ?Carbon $desde = null, ?Carbon $hasta = null): Collection
    {
        return Cliente::query()
            ->withCount(['ordenesTrabajo' => fn ($q) => $q
                ->when($desde, fn ($q2) => $q2->whereDate('created_at', '>=', $desde))
                ->when($hasta, fn ($q2) => $q2->whereDate('created_at', '<=', $hasta))])
            ->having('ordenes_trabajo_count', '>', 0)
            ->orderByDesc('ordenes_trabajo_count')
            ->limit($limite)
            ->get()
            ->map(fn (Cliente $c) => [
                'cliente_id' => $c->id,
                'nombre' => $c->nombre,
                'ot_count' => $c->ordenes_trabajo_count,
            ]);
    }

    /** Clientes nuevos (creados) dentro del rango. */
    public function clientesNuevos(?Carbon $desde = null, ?Carbon $hasta = null): int
    {
        return Cliente::query()
            ->when($desde, fn ($q) => $q->whereDate('created_at', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('created_at', '<=', $hasta))
            ->count();
    }
}
