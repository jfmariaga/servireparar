<?php

use App\Models\Tecnico;
use App\Services\Personal\DesempenoTecnicoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Desempeño'])] class extends Component
{
    public string $desde = '';
    public string $hasta = '';

    public function mount(): void
    {
        Gate::authorize('manage', Tecnico::class);
        $this->hasta = Carbon::today()->toDateString();
        $this->desde = Carbon::today()->subMonths(1)->toDateString();
    }

    public function with(): array
    {
        $desde = $this->desde !== '' ? Carbon::parse($this->desde) : null;
        $hasta = $this->hasta !== '' ? Carbon::parse($this->hasta) : null;

        return [
            'resumen' => (new DesempenoTecnicoService())->resumenPorTecnico($desde, $hasta)
                ->sortByDesc('tareas_finalizadas')
                ->values(),
        ];
    }

    public function limpiarFiltro(): void
    {
        $this->reset(['desde', 'hasta']);
    }
}; ?>

<div>
    @include('partials.usuarios-tabs')

    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6 max-w-2xl">
        Tiempo de ejecución, participación en Órdenes de Trabajo y cumplimiento de plazo por técnico
        (spec 004, FR-003), calculado sobre tareas finalizadas en el rango de fechas seleccionado.
    </p>

    <div class="flex flex-wrap items-end gap-4 mb-6">
        <div>
            <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1.5">Desde</label>
            <input type="date" wire:model.live="desde" class="border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2 text-sm outline-none focus:border-brand-blue">
        </div>
        <div>
            <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1.5">Hasta</label>
            <input type="date" wire:model.live="hasta" class="border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2 text-sm outline-none focus:border-brand-blue">
        </div>
        <button wire:click="limpiarFiltro" class="text-[13.5px] font-semibold px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">
            Limpiar
        </button>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Técnico</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">OT</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Tareas finalizadas</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Tiempo promedio (días)</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Cumplimiento de plazo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($resumen as $fila)
                    <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-5 py-3 font-medium">{{ $fila['nombre'] }}</td>
                        <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $fila['ot_count'] }}</td>
                        <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $fila['tareas_finalizadas'] }}</td>
                        <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $fila['tiempo_promedio_dias'] ?? '—' }}</td>
                        <td class="px-5 py-3">
                            @if ($fila['cumplimiento_pct'] !== null)
                                <span class="inline-block px-2.5 py-1 rounded-full text-[11.5px] font-semibold {{ $fila['cumplimiento_pct'] >= 80 ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10' : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10' }}">
                                    {{ $fila['cumplimiento_pct'] }}%
                                </span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">Sin tareas finalizadas en el rango seleccionado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
