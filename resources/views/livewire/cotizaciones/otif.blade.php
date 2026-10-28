<?php

use App\Models\Cotizacion;
use App\Services\Cotizaciones\OtifService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'OTIF'])] class extends Component
{
    public string $desde = '';

    public string $hasta = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Cotizacion::class);
    }

    public function limpiarFiltro(): void
    {
        $this->reset(['desde', 'hasta']);
    }

    public function with(): array
    {
        $desde = $this->desde !== '' ? Carbon::parse($this->desde) : null;
        $hasta = $this->hasta !== '' ? Carbon::parse($this->hasta) : null;

        $otif = app(OtifService::class);

        return [
            'resumen' => $otif->calcular($desde, $hasta),
            'detalle' => $otif->detalle($desde, $hasta),
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-breadcrumbs :items="[['label' => 'Cotizaciones', 'route' => 'cotizaciones.tablero'], ['label' => 'OTIF']]" />

    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1.5">Desde</label>
            <x-input-date wire:model.live="desde" />
        </div>
        <div>
            <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1.5">Hasta</label>
            <x-input-date wire:model.live="hasta" />
        </div>
        <button wire:click="limpiarFiltro" class="text-[13.5px] font-semibold px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">
            Limpiar
        </button>
        <p class="text-xs text-slate-400 ml-auto">Filtra por fecha real de entrega. Solo se incluyen despachos entregados con fecha prometida registrada.</p>
    </div>

    @php
        $nivelEstilo = [
            'plan_choque' => 'bg-brand-red-tint text-brand-red',
            'plan_corto_plazo' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
            'mantener_mejorar' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        ];
        $nivelTexto = [
            'plan_choque' => 'OTIF < 75% — plan de choque',
            'plan_corto_plazo' => 'OTIF 75–85% — plan corto plazo',
            'mantener_mejorar' => 'OTIF > 85% — mantener y mejorar',
        ];
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">% On Time</p>
            <p class="text-3xl font-bold mt-1">{{ $resumen['pct_on_time'] !== null ? $resumen['pct_on_time'].'%' : '—' }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ $resumen['on_time'] }} de {{ $resumen['total'] }} entregas a tiempo</p>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">% In Full</p>
            <p class="text-3xl font-bold mt-1">{{ $resumen['pct_in_full'] !== null ? $resumen['pct_in_full'].'%' : '—' }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ $resumen['in_full'] }} de {{ $resumen['total'] }} entregas completas</p>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">% OTIF</p>
            <p class="text-3xl font-bold mt-1">{{ $resumen['pct_otif'] !== null ? $resumen['pct_otif'].'%' : '—' }}</p>
            @if ($resumen['nivel'])
                <span class="inline-block mt-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold {{ $nivelEstilo[$resumen['nivel']] }}">{{ $nivelTexto[$resumen['nivel']] }}</span>
            @endif
        </div>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 font-bold text-sm text-slate-700 dark:text-slate-200">Detalle por despacho</div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Solicitud</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Cotización</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Fecha prometida</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Fecha real</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Días diferencia</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">On time</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">In full</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($detalle as $s)
                    <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                        <td class="px-5 py-3 font-mono font-semibold">
                            <a href="{{ route('despachos.detalle', $s) }}" wire:navigate class="text-brand-blue hover:underline">{{ $s->numero }}</a>
                        </td>
                        <td class="px-5 py-3">
                            @if ($s->cotizacion)
                                <a href="{{ route('cotizaciones.gestionar', $s->cotizacion) }}" wire:navigate class="text-brand-blue hover:underline">{{ $s->cotizacion->numero }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-5 py-3">{{ $s->fecha_promesa_entrega->format('d/m/Y') }}</td>
                        <td class="px-5 py-3">{{ $s->entregada_en->format('d/m/Y') }}</td>
                        <td class="px-5 py-3">{{ $s->diasDiferenciaEntrega() }}</td>
                        <td class="px-5 py-3">
                            <span @class(['font-semibold', 'text-emerald-600' => $s->entregaATiempo(), 'text-brand-red' => ! $s->entregaATiempo()])>
                                {{ $s->entregaATiempo() ? 'Sí' : 'No' }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <span @class(['font-semibold', 'text-emerald-600' => $s->entrega_completa, 'text-brand-red' => ! $s->entrega_completa])>
                                {{ $s->entrega_completa ? 'Sí' : 'No' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-slate-400">Sin despachos entregados con fecha prometida en el rango seleccionado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
