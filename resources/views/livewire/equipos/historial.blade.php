<?php

use App\Models\Equipo;
use App\Services\Equipos\EquipoHistorialService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Historial del equipo'])] class extends Component
{
    public Equipo $equipo;

    public const ESTADOS = [
        'operativo' => 'Operativo',
        'en_reparacion' => 'En reparación',
        'fuera_de_servicio' => 'Fuera de servicio',
        'de_baja' => 'De baja',
    ];

    public function mount(Equipo $equipo): void
    {
        $this->equipo = $equipo;
        Gate::authorize('view', $this->equipo);
    }

    public function with(EquipoHistorialService $historial): array
    {
        $this->equipo->load(['cliente', 'mantenimientoPreventivo']);

        return [
            'historial' => $historial->historial($this->equipo),
            'estados' => self::ESTADOS,
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-breadcrumbs :items="[['label' => 'Equipos', 'route' => 'equipos.index'], ['label' => $equipo->tipo]]" />

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-xl font-bold font-display">{{ $equipo->tipo }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $equipo->cliente->nombre }}</p>
            </div>
            <span @class([
                'inline-block px-2.5 py-1 rounded-full text-[11.5px] font-semibold whitespace-nowrap',
                'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10' => $equipo->estado === 'operativo',
                'bg-amber-50 text-amber-600 dark:bg-amber-500/10' => $equipo->estado === 'en_reparacion',
                'bg-slate-100 text-slate-500 dark:bg-slate-800' => in_array($equipo->estado, ['fuera_de_servicio', 'de_baja']),
            ])>
                {{ $estados[$equipo->estado] ?? $equipo->estado }}
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-5 text-sm">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Marca / Modelo</p>
                <p class="mt-0.5">{{ trim(($equipo->marca ?? '').' '.($equipo->modelo ?? '')) ?: '—' }}</p>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Serie</p>
                <p class="mt-0.5">{{ $equipo->serie ?: '—' }}</p>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Ubicación</p>
                <p class="mt-0.5">{{ $equipo->ubicacion ?: '—' }}</p>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Próximo mantenimiento</p>
                <p class="mt-0.5">{{ $equipo->mantenimientoPreventivo?->proxima_fecha?->format('d/m/Y') ?? '—' }}</p>
            </div>
        </div>
    </div>

    <div class="flex flex-col gap-4">
        <h2 class="font-bold text-sm">Historial técnico <span class="text-slate-400 font-normal">({{ $historial->count() }})</span></h2>

        @if ($historial->isEmpty())
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 text-sm text-slate-400 text-center">
                Este equipo todavía no tiene Órdenes de Trabajo registradas.
            </div>
        @else
            @foreach ($historial as $ot)
                @php
                    $tecnicos = $ot->tareas->pluck('tecnico.usuario.name')->filter()->unique()->values();
                @endphp
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 flex flex-col gap-3">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <a href="{{ route('ordenes-trabajo.detalle', $ot->id) }}" wire:navigate class="font-semibold text-brand-blue text-sm">{{ $ot->numero_ot }}</a>
                        <span class="text-xs text-slate-400">{{ $ot->created_at->format('d/m/Y') }} · {{ $ot->estado?->nombre }}</span>
                    </div>

                    <p class="text-sm">{{ $ot->descripcion }}</p>

                    <p class="text-xs text-slate-400">
                        Técnico(s): {{ $tecnicos->isNotEmpty() ? $tecnicos->implode(', ') : 'Sin técnico asignado' }}
                    </p>

                    @if ($ot->variablesTecnicas->isNotEmpty())
                        <div>
                            <h3 class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1.5">Variables técnicas</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($ot->variablesTecnicas as $variable)
                                    <span class="inline-flex items-center gap-1 text-xs bg-slate-50 dark:bg-slate-800 rounded-full px-3 py-1">
                                        <span class="font-semibold">{{ $variable->nombre }}:</span> {{ $variable->valor }} {{ $variable->unidad }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($ot->checklistTecnico->isNotEmpty())
                        <div>
                            <h3 class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1.5">Checklist técnico</h3>
                            <div class="flex flex-col gap-1">
                                @foreach ($ot->checklistTecnico as $item)
                                    <div class="flex items-center justify-between gap-3 text-xs">
                                        <span>{{ $item->item }}</span>
                                        <span class="font-semibold shrink-0 {{ $item->cumple === true ? 'text-emerald-600 dark:text-emerald-400' : ($item->cumple === false ? 'text-brand-red' : 'text-slate-400') }}">
                                            {{ $item->cumple === true ? 'Sí' : ($item->cumple === false ? 'No' : 'Pendiente') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($ot->evidencias->isNotEmpty())
                        <div>
                            <h3 class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1.5">Evidencias ({{ $ot->evidencias->count() }})</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($ot->evidencias as $ev)
                                    @php $esImg = str((string) $ev->tipo_archivo)->startsWith('image/'); @endphp
                                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($ev->url_archivo) }}" target="_blank"
                                       class="block w-12 h-12 rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
                                        @if ($esImg)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($ev->url_archivo) }}" class="w-full h-full object-cover" alt="Evidencia">
                                        @else
                                            <span class="w-full h-full flex items-center justify-center text-slate-400 text-[10px]">Archivo</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</div>
