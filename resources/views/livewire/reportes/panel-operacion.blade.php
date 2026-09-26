<?php

use App\Models\Tecnico;
use App\Services\Personal\DesempenoTecnicoService;
use App\Services\Reportes\IndicadoresAgregadosService;
use Illuminate\Support\Carbon;
use Livewire\Volt\Component;

/**
 * Panel de operación de Administrador/Jefe de Taller (spec 007, US1/US2,
 * extendido): indicadores, piso en tiempo real y los drill-downs (OT por
 * categoría, desempeño por técnico) que antes navegaban a otra página.
 * Se embebe desde dashboard.blade.php para que ese archivo no cargue con
 * todo el estado de los modales.
 */
new class extends Component
{
    public ?string $modal = null; // 'ot' | 'desempeno' | 'herramientas' | 'prestamos' | 'stock' | 'estancadas' | 'tecnicos' | 'despachos'

    public ?string $categoriaOt = null;

    public ?int $tecnicoId = null;

    public string $desde = '';

    public string $hasta = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['Administrador', 'Jefe de Taller']), 403);

        $this->hasta = Carbon::today()->toDateString();
        $this->desde = Carbon::today()->subMonths(1)->toDateString();
    }

    public function abrirOt(string $categoria): void
    {
        $this->modal = 'ot';
        $this->categoriaOt = $categoria;
    }

    public function abrirSimple(string $modal): void
    {
        $this->modal = $modal;
    }

    public function abrirDesempeno(): void
    {
        if (! auth()->user()->can('manage-tecnicos')) {
            return;
        }

        $this->modal = 'desempeno';
        $this->tecnicoId = null;
    }

    public function verTecnico(int $tecnicoId): void
    {
        $this->tecnicoId = $tecnicoId;
    }

    public function volverListaTecnicos(): void
    {
        $this->tecnicoId = null;
    }

    public function limpiarFiltroDesempeno(): void
    {
        $this->reset(['desde', 'hasta']);
    }

    public function cerrarModal(): void
    {
        $this->modal = null;
        $this->categoriaOt = null;
        $this->tecnicoId = null;
    }

    public function with(): array
    {
        $indicadores = new IndicadoresAgregadosService();
        $tendencia = $indicadores->tendenciaOtEnCurso();

        // Auto-refresh vía wire:poll (spec 007, sesión 2026-09-15); la
        // gráfica de tendencia vive en JS (Chart.js) y se actualiza por
        // evento en vez de recrear el <canvas> en cada poll.
        $this->dispatch('piso-tendencia-actualizada', data: $tendencia);

        $data = [
            'verDesempeno' => auth()->user()->can('manage-tecnicos'),
            'otResumen' => $indicadores->otResumen(),
            'cumplimientoTiempos' => $indicadores->cumplimientoTiempos(),
            'productividad' => $indicadores->productividadEquipo(),
            'otsDelPiso' => $indicadores->otsDelPiso(),
            'tendenciaOtEnCurso' => $tendencia,
            'herramientasPendientes' => $indicadores->herramientasPendientesGestion(),
            'prestamosSinDevolver' => $indicadores->prestamosSinDevolver(),
            'stockBajo' => $indicadores->stockBajo(),
            'despachosPendientes' => $indicadores->despachosPendientes(),
            'tecnicosDisponibilidad' => $indicadores->tecnicosDisponibilidad(),
            'otEstancadas' => $indicadores->otEstancadas(),
        ];

        if ($this->modal === 'ot' && $this->categoriaOt) {
            $data['otModalTitulo'] = [
                'abiertas' => 'OT abiertas',
                'vencidas' => 'OT vencidas',
                'proximas_a_vencer' => 'Próximas a vencer',
                'cerradas' => 'OT cerradas',
            ][$this->categoriaOt] ?? 'OT';
            $data['otModalLista'] = $indicadores->otEnCategoria($this->categoriaOt);
        }

        if ($this->modal === 'desempeno' && $data['verDesempeno']) {
            $desde = $this->desde !== '' ? Carbon::parse($this->desde) : null;
            $hasta = $this->hasta !== '' ? Carbon::parse($this->hasta) : null;
            $desempeno = new DesempenoTecnicoService();

            if ($this->tecnicoId) {
                $tecnico = Tecnico::query()->with('usuario:id,name')->find($this->tecnicoId);
                $data['tecnicoSeleccionado'] = $tecnico;
                $data['tecnicoResumen'] = $tecnico ? $desempeno->resumen($tecnico, $desde, $hasta) : null;
                $data['tecnicoTareas'] = $tecnico ? $desempeno->tareasActivas($tecnico) : collect();
            } else {
                $data['desempenoPorTecnico'] = $desempeno->resumenPorTecnico($desde, $hasta)
                    ->sortByDesc('tareas_finalizadas')
                    ->values();
            }
        }

        return $data;
    }
}; ?>

<div class="flex flex-col gap-6" wire:poll.5s>
    <div>
        <h1 class="text-xl font-bold font-display">Indicadores de operación</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            Hola, {{ auth()->user()->name }}. Se actualiza solo cada 5 s (spec 007).
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Cumplimiento de tiempos</p>
            <p class="text-2xl font-bold mt-1">{{ $cumplimientoTiempos !== null ? $cumplimientoTiempos.'%' : '—' }}</p>
            <p class="text-xs text-slate-400 mt-1">OT cerradas dentro del tiempo estimado</p>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Productividad del equipo</p>
            <p class="text-2xl font-bold mt-1">{{ $productividad['cumplimiento_promedio_pct'] !== null ? $productividad['cumplimiento_promedio_pct'].'%' : '—' }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ $productividad['tecnicos_activos'] }} técnico(s) · {{ $productividad['tareas_finalizadas_total'] }} tarea(s) finalizada(s)</p>
        </div>
        @if ($verDesempeno)
            <button type="button" wire:click="abrirDesempeno"
                    class="text-left bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 hover:ring-2 hover:ring-brand-blue/20 flex flex-col justify-center">
                <p class="text-sm font-semibold text-brand-blue">Ver desempeño por técnico →</p>
                <p class="text-xs text-slate-400 mt-1">Detalle con filtro de fechas</p>
            </button>
        @endif
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        @foreach ([['abiertas', 'OT abiertas', $otResumen['abiertas'], 'text-slate-600 dark:text-slate-300'], ['vencidas', 'OT vencidas', $otResumen['vencidas'], 'text-brand-red'], ['proximas_a_vencer', 'Próximas a vencer', $otResumen['proximas_a_vencer'], 'text-amber-600 dark:text-amber-400'], ['cerradas', 'OT cerradas', $otResumen['cerradas'], 'text-emerald-600 dark:text-emerald-400']] as [$categoria, $label, $n, $tono])
            <button type="button" wire:click="abrirOt('{{ $categoria }}')"
                    class="text-left bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 hover:ring-2 hover:ring-brand-blue/20">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p>
                <p class="text-2xl font-bold mt-1 {{ $tono }}">{{ $n }}</p>
            </button>
        @endforeach
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <button type="button" wire:click="abrirSimple('herramientas')"
                class="text-left bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 hover:ring-2 hover:ring-brand-blue/20">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Herramientas por gestionar</p>
            <p class="text-2xl font-bold mt-1 {{ $herramientasPendientes->isNotEmpty() ? 'text-brand-red' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $herramientasPendientes->count() }}</p>
            <p class="text-xs text-slate-400 mt-1">Dañadas o en mantenimiento</p>
        </button>
        <button type="button" wire:click="abrirSimple('prestamos')"
                class="text-left bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 hover:ring-2 hover:ring-brand-blue/20">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Préstamos sin devolver</p>
            <p class="text-2xl font-bold mt-1">{{ $prestamosSinDevolver->count() }}</p>
            <p class="text-xs text-slate-400 mt-1">Herramientas entregadas a técnicos</p>
        </button>
        <button type="button" wire:click="abrirSimple('stock')"
                class="text-left bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 hover:ring-2 hover:ring-brand-blue/20">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Stock bajo</p>
            <p class="text-2xl font-bold mt-1 {{ $stockBajo->isNotEmpty() ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $stockBajo->count() }}</p>
            <p class="text-xs text-slate-400 mt-1">Consumibles bajo el mínimo</p>
        </button>
        <button type="button" wire:click="abrirSimple('despachos')"
                class="text-left bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 hover:ring-2 hover:ring-brand-blue/20">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Despachos pendientes</p>
            <p class="text-2xl font-bold mt-1 {{ $despachosPendientes->isNotEmpty() ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $despachosPendientes->count() }}</p>
            <p class="text-xs text-slate-400 mt-1">Por gestionar o con mensajero</p>
        </button>
        <button type="button" wire:click="abrirSimple('tecnicos')"
                class="text-left bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 hover:ring-2 hover:ring-brand-blue/20">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Técnicos ahora</p>
            <p class="text-2xl font-bold mt-1">{{ $tecnicosDisponibilidad['libres'] }} <span class="text-sm font-normal text-slate-400">libres</span></p>
            <p class="text-xs text-slate-400 mt-1">{{ $tecnicosDisponibilidad['ocupados'] }} ocupado(s)</p>
        </button>
    </div>

    @if ($otEstancadas->isNotEmpty())
        <button type="button" wire:click="abrirSimple('estancadas')"
                class="text-left bg-white dark:bg-slate-900 border border-amber-200 dark:border-amber-900/50 rounded-2xl p-5 hover:ring-2 hover:ring-brand-blue/20">
            <p class="text-[11px] font-bold uppercase tracking-wide text-amber-600 dark:text-amber-400">OT sin actividad reciente</p>
            <p class="text-2xl font-bold mt-1">{{ $otEstancadas->count() }}</p>
            <p class="text-xs text-slate-400 mt-1">Tareas en curso sin avance en los últimos 3 días</p>
        </button>
    @endif

    {{-- Piso en tiempo real: tendencia de concurrencia + tarjetas por tarea en curso o por iniciar --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5"
         x-data="serviopsPisoChart(@js($tendenciaOtEnCurso))">
        <h2 class="font-bold text-sm mb-3">Tendencia OT en curso <span class="text-slate-400 font-normal">(últimas 24 h)</span></h2>
        <div class="h-56" wire:ignore>
            <canvas x-ref="canvas"></canvas>
        </div>
    </div>

    @php
        $enTaller = $otsDelPiso->filter(fn ($ot) => $ot->tipo_servicio === 'taller')->values();
        $aDomicilio = $otsDelPiso->filter(fn ($ot) => $ot->tipo_servicio === 'domicilio')->values();
    @endphp

    @foreach ([['En el taller', $enTaller], ['A domicilio', $aDomicilio]] as [$tituloPiso, $listaPiso])
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 flex flex-col gap-3">
            <h2 class="font-bold text-sm">{{ $tituloPiso }} <span class="text-slate-400 font-normal">({{ $listaPiso->count() }})</span></h2>

            @if ($listaPiso->isEmpty())
                <p class="text-xs text-slate-400">Sin OT en curso ni por iniciar en este momento.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($listaPiso as $ot)
                        @php
                            // Avance = tareas finalizadas / total de la OT (equivalente al "OEE"
                            // de la tarjeta: cuánto lleva hecho, en vez de una métrica inventada).
                            $avancePct = $ot->tareas_total > 0 ? (int) round($ot->tareas_finalizadas_count / $ot->tareas_total * 100) : 0;
                            $tono = $avancePct > 80 ? ['text-emerald-500', 'text-emerald-600 dark:text-emerald-400'] : ($avancePct >= 40 ? ['text-amber-500', 'text-amber-600 dark:text-amber-400'] : ['text-slate-400', 'text-slate-500']);

                            // Plazo consumido a nivel de OT: creada + tiempo_estimado_dias vs hoy.
                            $plazoPct = null;
                            if ($ot->tiempo_estimado_dias !== null) {
                                $limiteOt = $ot->created_at->copy()->addDays((float) $ot->tiempo_estimado_dias);
                                $totalSeg = $limiteOt->getTimestamp() - $ot->created_at->getTimestamp();
                                $plazoPct = $totalSeg > 0 ? min(999, max(0, round((now()->getTimestamp() - $ot->created_at->getTimestamp()) / $totalSeg * 100))) : null;
                            }
                            $plazoBarColor = $plazoPct === null ? 'bg-slate-300 dark:bg-slate-700' : ($plazoPct > 100 ? 'bg-brand-red' : ($plazoPct >= 70 ? 'bg-amber-500' : 'bg-emerald-500'));

                            $bloqueada = $ot->tareas->contains(fn ($t) => $t->bloqueadaPorInsumos() || $t->prerrequisitosPendientes()->isNotEmpty());
                            $tecnicos = $ot->tareas->pluck('tecnico.usuario.name')->filter()->unique()->values();
                            $inicioMasTemprano = $ot->tareas->pluck('fecha_inicio')->filter()->sort()->first();
                        @endphp
                        <a href="{{ route('ordenes-trabajo.detalle', $ot->id) }}" wire:navigate wire:key="piso-ot-{{ $ot->id }}"
                           class="border border-slate-100 dark:border-slate-800 rounded-xl p-4 flex flex-col items-center gap-2 text-center hover:ring-2 hover:ring-brand-blue/20">
                            <div class="relative w-16 h-16 rounded-full flex items-center justify-center border-4 {{ $tono[0] }}" style="border-color: currentColor">
                                <span class="text-xs font-bold {{ $tono[1] }}">{{ $avancePct }}%</span>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $ot->estado?->slug === 'en_curso' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">
                                {{ $ot->estado?->slug === 'en_curso' ? 'En curso' : 'Por iniciar' }}
                            </span>
                            <span class="font-semibold text-brand-blue text-sm">{{ $ot->numero_ot }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $ot->cliente?->nombre }}</span>
                            <span class="text-[11px] text-slate-400">
                                {{ $tecnicos->isNotEmpty() ? $tecnicos->implode(', ') : 'Sin técnico asignado' }}
                            </span>
                            @if ($inicioMasTemprano)
                                <span class="text-[11px] text-slate-400">desde {{ $inicioMasTemprano->diffForHumans() }}</span>
                            @endif
                            @if ($ot->tipo_servicio === 'domicilio' && $ot->direccion_servicio)
                                <span class="text-[10px] text-slate-400">{{ \Illuminate\Support\Str::limit($ot->direccion_servicio, 40) }}</span>
                            @endif

                            <div class="w-full flex flex-col gap-1.5 mt-1.5 text-left">
                                <div>
                                    <div class="flex items-center justify-between text-[10px] text-slate-400">
                                        <span>Avance de tareas</span><span class="font-semibold">{{ $ot->tareas_finalizadas_count }}/{{ $ot->tareas_total }}</span>
                                    </div>
                                    <div class="h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden mt-0.5">
                                        <div class="h-full rounded-full {{ $tono[0] === 'text-emerald-500' ? 'bg-emerald-500' : ($tono[0] === 'text-amber-500' ? 'bg-amber-500' : 'bg-slate-400') }}" style="width: {{ $avancePct }}%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between text-[10px] text-slate-400">
                                        <span>Plazo consumido</span><span class="font-semibold">{{ $plazoPct !== null ? min(100, $plazoPct).'%' : '—' }}</span>
                                    </div>
                                    <div class="h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden mt-0.5">
                                        <div class="h-full rounded-full {{ $plazoBarColor }}" style="width: {{ $plazoPct !== null ? min(100, $plazoPct) : 0 }}%"></div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between text-[10px]">
                                    <span class="text-slate-400">Insumos / prerrequisitos</span>
                                    <span class="font-semibold {{ $bloqueada ? 'text-brand-red' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $bloqueada ? 'Bloqueada' : 'Libre' }}</span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach

    {{--
        Los dos modales se envuelven en un @if externo (no solo el `show` del
        componente) porque Blade evalúa el contenido del slot ANTES de pasarlo
        al componente: si dependiera solo de `:show`, `$otModalLista` etc. se
        intentarían leer igual en cada poll aunque el modal esté cerrado.
    --}}
    @if ($modal === 'ot')
    <x-modal :show="true" :title="$otModalTitulo" maxWidth="max-w-2xl">
        @if ($otModalLista->isEmpty())
            <p class="text-sm text-slate-400 py-6 text-center">No hay OT en esta categoría.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach ($otModalLista as $ot)
                    @php
                        $vencInfo = null;
                        if (in_array($categoriaOt, ['vencidas', 'proximas_a_vencer'], true) && $ot->tiempo_estimado_dias !== null) {
                            $limiteOt = \Illuminate\Support\Carbon::parse($ot->created_at)->addDays((float) $ot->tiempo_estimado_dias)->startOfDay();
                            $dias = (int) round(\Illuminate\Support\Carbon::today()->diffInDays($limiteOt, false));
                            $vencInfo = $dias < 0 ? 'venció hace '.abs($dias).' día(s)' : ($dias === 0 ? 'vence hoy' : 'vence en '.$dias.' día(s)');
                        }
                    @endphp
                    <a href="{{ route('ordenes-trabajo.detalle', $ot->id) }}" wire:navigate
                       class="grid sm:grid-cols-[6rem_1fr_auto] gap-2 sm:gap-4 items-center text-sm border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3 hover:ring-2 hover:ring-brand-blue/20">
                        <span class="font-semibold text-brand-blue">{{ $ot->numero_ot }}</span>
                        <span>
                            {{ $ot->cliente?->nombre }}
                            <span class="block text-xs text-slate-400 mt-0.5">
                                {{ $ot->estado?->nombre }} · creada {{ $ot->created_at->format('d/m/Y') }}
                            </span>
                        </span>
                        @if ($vencInfo)
                            <span class="shrink-0 text-[11px] font-semibold {{ $categoriaOt === 'vencidas' ? 'text-brand-red' : 'text-amber-600 dark:text-amber-400' }}">{{ $vencInfo }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </x-modal>
    @endif

    @if ($modal === 'herramientas')
    <x-modal :show="true" title="Herramientas por gestionar" maxWidth="max-w-2xl">
        @if ($herramientasPendientes->isEmpty())
            <p class="text-sm text-slate-400 py-6 text-center">Sin herramientas dañadas ni en mantenimiento.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach ($herramientasPendientes as $item)
                    <div class="grid sm:grid-cols-[1fr_auto] gap-2 items-center text-sm border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3">
                        <span>{{ $item->nombre }} <span class="text-xs text-slate-400">({{ $item->codigo }})</span></span>
                        <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $item->estado_herramienta === 'dañada' ? 'bg-red-100 text-brand-red dark:bg-red-900/30' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }}">
                            {{ $item->estado_herramienta === 'dañada' ? 'Dañada' : 'En mantenimiento' }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-modal>
    @endif

    @if ($modal === 'prestamos')
    <x-modal :show="true" title="Préstamos sin devolver" maxWidth="max-w-2xl">
        @if ($prestamosSinDevolver->isEmpty())
            <p class="text-sm text-slate-400 py-6 text-center">No hay préstamos activos.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach ($prestamosSinDevolver as $prestamo)
                    <div class="grid sm:grid-cols-[1fr_auto] gap-2 items-center text-sm border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3">
                        <span>
                            {{ $prestamo->inventario?->nombre }}
                            <span class="block text-xs text-slate-400 mt-0.5">{{ $prestamo->tecnico?->usuario?->name ?? 'Técnico #'.$prestamo->tecnico_id }}</span>
                        </span>
                        <span class="shrink-0 text-[11px] text-slate-400">desde {{ $prestamo->solicitada_en?->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-modal>
    @endif

    @if ($modal === 'stock')
    <x-modal :show="true" title="Stock bajo" maxWidth="max-w-2xl">
        @if ($stockBajo->isEmpty())
            <p class="text-sm text-slate-400 py-6 text-center">Ningún consumible está por debajo del mínimo.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach ($stockBajo as $item)
                    <div class="grid sm:grid-cols-[1fr_auto] gap-2 items-center text-sm border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3">
                        <span>{{ $item->nombre }} <span class="text-xs text-slate-400">({{ $item->codigo }})</span></span>
                        <span class="shrink-0 text-[11px] font-semibold text-amber-600 dark:text-amber-400">{{ $item->stock_actual }} / {{ $item->stock_minimo }} {{ $item->unidadMedida?->abreviatura }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-modal>
    @endif

    @if ($modal === 'despachos')
    <x-modal :show="true" title="Despachos pendientes" maxWidth="max-w-2xl">
        @if ($despachosPendientes->isEmpty())
            <p class="text-sm text-slate-400 py-6 text-center">No hay despachos pendientes.</p>
        @else
            @php
                $etiquetasEstado = [
                    'solicitada' => 'Solicitada', 'recibida' => 'Recibida',
                    'remisionada' => 'Remisionada', 'despachada' => 'Con mensajero',
                ];
                $tonoEstado = [
                    'solicitada' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                    'recibida' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                    'remisionada' => 'bg-brand-blue-tint text-brand-blue',
                    'despachada' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                ];
            @endphp
            <div class="flex flex-col gap-2">
                @foreach ($despachosPendientes as $s)
                    <div class="grid sm:grid-cols-[1fr_auto] gap-2 items-center text-sm border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3">
                        <span>
                            {{ $s->numero }} · {{ $s->cliente?->nombre }}
                            <span class="block text-xs text-slate-400 mt-0.5">desde {{ $s->fecha_solicitud->diffForHumans() }}</span>
                        </span>
                        <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $tonoEstado[$s->estado] ?? '' }}">
                            {{ $etiquetasEstado[$s->estado] ?? $s->estado }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-modal>
    @endif

    @if ($modal === 'estancadas')
    <x-modal :show="true" title="OT sin actividad reciente" maxWidth="max-w-2xl">
        @if ($otEstancadas->isEmpty())
            <p class="text-sm text-slate-400 py-6 text-center">No hay tareas estancadas.</p>
        @else
            <div class="flex flex-col gap-2">
                @foreach ($otEstancadas as $tarea)
                    <a href="{{ route('ordenes-trabajo.detalle', $tarea->ot_id) }}" wire:navigate
                       class="grid sm:grid-cols-[6rem_1fr_auto] gap-2 sm:gap-4 items-center text-sm border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3 hover:ring-2 hover:ring-brand-blue/20">
                        <span class="font-semibold text-brand-blue">{{ $tarea->ordenTrabajo?->numero_ot }}</span>
                        <span>
                            {{ $tarea->descripcion }}
                            <span class="block text-xs text-slate-400 mt-0.5">{{ $tarea->ordenTrabajo?->cliente?->nombre }} · {{ $tarea->tecnico?->usuario?->name }}</span>
                        </span>
                        <span class="shrink-0 text-[11px] font-semibold text-amber-600 dark:text-amber-400">sin avance desde {{ $tarea->updated_at->diffForHumans() }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-modal>
    @endif

    @if ($modal === 'tecnicos')
    <x-modal :show="true" title="Técnicos ahora" maxWidth="max-w-2xl">
        <div class="flex flex-col gap-5">
            <div>
                <h4 class="font-bold text-xs uppercase tracking-wide text-slate-400 mb-2">Ocupados ({{ $tecnicosDisponibilidad['ocupadosLista']->count() }})</h4>
                @forelse ($tecnicosDisponibilidad['ocupadosLista'] as $t)
                    <div class="grid sm:grid-cols-[1fr_auto] gap-2 items-center text-sm border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3 mb-2">
                        <span>{{ $t['nombre'] }}</span>
                        <span class="shrink-0 text-[11px] font-semibold text-amber-600 dark:text-amber-400">{{ $t['tareas_activas'] }} tarea(s) activa(s)</span>
                    </div>
                @empty
                    <p class="text-xs text-slate-400">Ningún técnico ocupado en este momento.</p>
                @endforelse
            </div>
            <div>
                <h4 class="font-bold text-xs uppercase tracking-wide text-slate-400 mb-2">Libres ({{ $tecnicosDisponibilidad['libresLista']->count() }})</h4>
                @forelse ($tecnicosDisponibilidad['libresLista'] as $t)
                    <div class="text-sm border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3 mb-2">
                        <span>{{ $t['nombre'] }}</span>
                    </div>
                @empty
                    <p class="text-xs text-slate-400">Ningún técnico libre en este momento.</p>
                @endforelse
            </div>
        </div>
    </x-modal>
    @endif

    @if ($modal === 'desempeno' && $verDesempeno)
    <x-modal :show="true" :title="$tecnicoId && $tecnicoSeleccionado ? ($tecnicoSeleccionado->usuario?->name ?? 'Técnico') : 'Desempeño por técnico'" maxWidth="max-w-2xl">
        @if ($tecnicoId && $tecnicoSeleccionado)
            <button type="button" wire:click="volverListaTecnicos" class="text-xs font-semibold text-brand-blue mb-4">← Volver a la lista</button>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                @foreach ([['OT', $tecnicoResumen['ot_count']], ['Tareas finalizadas', $tecnicoResumen['tareas_finalizadas']], ['Tiempo prom. (días)', $tecnicoResumen['tiempo_promedio_dias'] ?? '—'], ['Cumplimiento', $tecnicoResumen['cumplimiento_pct'] !== null ? $tecnicoResumen['cumplimiento_pct'].'%' : '—']] as [$label, $valor])
                    <div class="border border-slate-100 dark:border-slate-800 rounded-xl p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p>
                        <p class="text-lg font-bold mt-0.5">{{ $valor }}</p>
                    </div>
                @endforeach
            </div>

            <h4 class="font-bold text-xs uppercase tracking-wide text-slate-400 mb-2">Tareas actuales ({{ $tecnicoTareas->count() }})</h4>
            <div class="flex flex-col gap-2">
                @forelse ($tecnicoTareas as $t)
                    <a href="{{ route('ordenes-trabajo.detalle', $t->ot_id) }}" wire:navigate
                       class="grid sm:grid-cols-[6rem_1fr_auto] gap-2 sm:gap-4 items-center text-sm border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3 hover:ring-2 hover:ring-brand-blue/20">
                        <span class="font-semibold text-brand-blue">{{ $t->ordenTrabajo?->numero_ot }}</span>
                        <span>
                            {{ $t->descripcion }}
                            <span class="block text-xs text-slate-400 mt-0.5">{{ $t->ordenTrabajo?->cliente?->nombre }}</span>
                        </span>
                        <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $t->estado_tarea === 'en_curso' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">
                            {{ $t->estado_tarea === 'en_curso' ? 'En curso' : 'Por iniciar' }}
                        </span>
                    </a>
                @empty
                    <p class="text-xs text-slate-400">Sin tareas activas en este momento.</p>
                @endforelse
            </div>
        @else
            <div class="flex flex-wrap items-end gap-4 mb-4">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1.5">Desde</label>
                    <input type="date" wire:model.live="desde" class="border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2 text-sm outline-none focus:border-brand-blue">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1.5">Hasta</label>
                    <input type="date" wire:model.live="hasta" class="border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2 text-sm outline-none focus:border-brand-blue">
                </div>
                <button wire:click="limpiarFiltroDesempeno" class="text-[13.5px] font-semibold px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">
                    Limpiar
                </button>
            </div>

            <div class="flex flex-col gap-1">
                @forelse (($desempenoPorTecnico ?? collect()) as $fila)
                    <button type="button" wire:click="verTecnico({{ $fila['tecnico_id'] }})"
                            class="w-full text-left grid grid-cols-[1fr_auto_auto_auto] gap-3 items-center text-sm border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3 hover:ring-2 hover:ring-brand-blue/20">
                        <span class="font-medium">{{ $fila['nombre'] }}</span>
                        <span class="text-xs text-slate-400">{{ $fila['tareas_finalizadas'] }} tarea(s)</span>
                        <span class="text-xs text-slate-400">{{ $fila['tiempo_promedio_dias'] ?? '—' }} día(s) prom.</span>
                        <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $fila['cumplimiento_pct'] !== null ? ($fila['cumplimiento_pct'] >= 80 ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10' : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10') : 'text-slate-400' }}">
                            {{ $fila['cumplimiento_pct'] !== null ? $fila['cumplimiento_pct'].'%' : '—' }}
                        </span>
                    </button>
                @empty
                    <p class="text-sm text-slate-400 py-6 text-center">Sin tareas finalizadas en el rango seleccionado.</p>
                @endforelse
            </div>
        @endif
    </x-modal>
    @endif
</div>
