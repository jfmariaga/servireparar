<?php

use App\Models\DetalleOt;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Inicio'])] class extends Component
{
    public function with(): array
    {
        $tecnico = auth()->user()->tecnico;

        if (! $tecnico) {
            return ['tecnico' => null, 'asignadas' => collect(), 'enCurso' => collect(), 'atrasadas' => collect()];
        }

        $tareas = DetalleOt::query()
            ->where('tecnico_id', $tecnico->id)
            ->whereIn('estado_tarea', ['pendiente', 'en_curso'])
            ->with(['ordenTrabajo:id,numero_ot,cliente_id,estado_id', 'ordenTrabajo.cliente:id,nombre', 'ordenTrabajo.estado:id,slug,nombre', 'ordenTrabajo.eventos', 'prerrequisitos', 'solicitudesInsumo'])
            ->orderBy('orden')->orderBy('id')
            ->get()
            // Solo tareas de OT ya liberadas o en ejecución (no en "Planificación").
            ->filter(fn (DetalleOt $t) => ! $t->ordenTrabajo?->estaEnEstado(\App\Models\EstadoOt::EN_REVISION)
                && ! optional($t->ordenTrabajo?->estado)->es_terminal);

        return [
            'tecnico' => $tecnico,
            'asignadas' => $tareas->where('estado_tarea', 'pendiente')->values(),
            'enCurso' => $tareas->where('estado_tarea', 'en_curso')->values(),
            'atrasadas' => $tareas->filter(fn (DetalleOt $t) => $t->estaAtrasada())->values(),
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    @if (! $tecnico)
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-7">
            <h1 class="text-xl font-bold mb-1.5">Bienvenido, {{ auth()->user()->name }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Rol(es): {{ auth()->user()->getRoleNames()->implode(', ') ?: 'Sin rol asignado' }}
            </p>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-4">
                Los dashboards por rol con indicadores se implementan en un módulo posterior.
                Por ahora usa el menú de navegación.
            </p>
        </div>
    @else
        @php $nfmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.'); @endphp

        <div>
            <h1 class="text-xl font-bold font-display">Mis tareas</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Hola, {{ auth()->user()->name }}. Estas son las tareas asignadas a ti.</p>
        </div>

        {{-- Resumen --}}
        <div class="grid grid-cols-3 gap-4">
            @foreach ([['Asignadas', $asignadas->count(), 'text-slate-600 dark:text-slate-300'], ['En curso', $enCurso->count(), 'text-amber-600 dark:text-amber-400'], ['Atrasadas', $atrasadas->count(), 'text-brand-red']] as [$label, $n, $tono])
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p>
                    <p class="text-2xl font-bold mt-1 {{ $tono }}">{{ $n }}</p>
                </div>
            @endforeach
        </div>

        {{-- Atrasadas destacadas --}}
        @if ($atrasadas->isNotEmpty())
            <div class="bg-red-50 dark:bg-red-900/15 border border-brand-red/30 rounded-2xl p-5 flex flex-col gap-3">
                <h2 class="font-bold text-sm text-brand-red">Atrasadas — atención inmediata</h2>
                @foreach ($atrasadas as $t)
                    <a href="{{ route('ordenes-trabajo.detalle', $t->ot_id) }}" wire:navigate
                       class="flex items-center justify-between gap-3 text-sm bg-white dark:bg-slate-900 rounded-xl px-4 py-3 hover:ring-2 hover:ring-brand-red/30">
                        <span>
                            <span class="font-semibold">{{ $t->ordenTrabajo?->numero_ot }}</span>
                            <span class="text-slate-500 dark:text-slate-400"> · {{ \Illuminate\Support\Str::limit($t->descripcion, 60) }}</span>
                        </span>
                        <span class="shrink-0 text-[11px] font-semibold text-brand-red">
                            venció {{ $t->fechaLimitePlazo()?->format('d/m/Y') }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Tabla + tarjetas por grupo --}}
        @foreach ([['En curso', $enCurso], ['Asignadas', $asignadas]] as [$titulo, $lista])
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 flex flex-col gap-3">
                <h2 class="font-bold text-sm">{{ $titulo }} <span class="text-slate-400 font-normal">({{ $lista->count() }})</span></h2>

                @forelse ($lista as $t)
                    @php
                        $limite = $t->fechaLimitePlazo();
                        $bloqueadaPrereq = $t->estado_tarea === 'pendiente' && $t->prerrequisitosPendientes()->isNotEmpty();
                        $bloqueadaInsumo = $t->bloqueadaPorInsumos();
                    @endphp
                    <a href="{{ route('ordenes-trabajo.detalle', $t->ot_id) }}" wire:navigate wire:key="dash-t-{{ $t->id }}"
                       class="grid sm:grid-cols-[7rem_1fr_auto] gap-2 sm:gap-4 items-start text-sm border border-slate-100 dark:border-slate-800 rounded-xl px-4 py-3 hover:ring-2 hover:ring-brand-blue/20">
                        <span class="font-semibold text-brand-blue">{{ $t->ordenTrabajo?->numero_ot }}</span>
                        <span>
                            {{ $t->descripcion }}
                            <span class="block text-xs text-slate-400 mt-0.5">
                                {{ $t->ordenTrabajo?->cliente?->nombre }}
                                @if ($t->dias_cumplimiento !== null) · plazo {{ $nfmt($t->dias_cumplimiento) }} día(s)@if ($limite) · vence {{ $limite->format('d/m/Y') }}@endif @endif
                            </span>
                        </span>
                        <span class="flex flex-wrap gap-1.5">
                            @if ($t->estaAtrasada())
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold bg-red-100 text-brand-red dark:bg-red-900/30">Atrasada</span>
                            @endif
                            @if ($bloqueadaInsumo)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Esperando insumos</span>
                            @elseif ($bloqueadaPrereq)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-500 dark:bg-slate-800">Bloqueada</span>
                            @endif
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold
                                {{ $t->estado_tarea === 'en_curso' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-slate-100 dark:bg-slate-800' }}">
                                {{ str($t->estado_tarea)->replace('_', ' ')->ucfirst() }}
                            </span>
                        </span>
                    </a>
                @empty
                    <p class="text-xs text-slate-400">Nada por aquí.</p>
                @endforelse
            </div>
        @endforeach
    @endif
</div>
