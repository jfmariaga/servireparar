<?php

use App\Models\Cotizacion;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layout', ['title' => 'Cotizaciones'])] class extends Component
{
    use WithPagination;

    #[Url]
    public string $estado = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Cotizacion::class);
    }

    public function with(): array
    {
        $cotizaciones = Cotizacion::query()
            ->with('cliente')
            ->withCount('detalles')
            ->when($this->estado !== '', fn ($q) => $q->where('estado', $this->estado))
            ->orderByDesc('created_at')
            ->paginate(12);

        return [
            'cotizaciones' => $cotizaciones,
            'estados' => ['en_revision', 'cotizada', 'aceptada', 'rechazada', 'entregada', 'facturada'],
            'sinClientePendientes' => Cotizacion::whereNull('cliente_id')->whereNotIn('estado', ['rechazada', 'facturada'])->count(),
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-breadcrumbs :items="[['label' => 'Cotizaciones']]" />

    @if ($sinClientePendientes)
        <div class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-sm rounded-xl px-4 py-3">
            Hay <strong>{{ $sinClientePendientes }}</strong> {{ $sinClientePendientes === 1 ? 'cotización sin cliente identificado' : 'cotizaciones sin cliente identificado' }} — ábrelas para asignarlo manualmente.
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <label class="text-sm font-semibold text-slate-500 dark:text-slate-400">Estado</label>
            <x-select wire:model.live="estado" :placeholder="'Todos'">
                @foreach ($estados as $e)
                    <option value="{{ $e }}">{{ ucfirst(str_replace('_', ' ', $e)) }}</option>
                @endforeach
            </x-select>
        </div>
        <a href="{{ route('cotizaciones.servicios') }}" class="text-sm font-semibold text-brand-blue hover:underline">Maestra de servicios</a>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Número</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Cliente</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Ítems</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Total</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Estado</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cotizaciones as $cot)
                    <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-5 py-3 font-mono font-semibold">{{ $cot->numero }}</td>
                        <td class="px-5 py-3">{{ $cot->cliente?->nombre ?? '— sin identificar —' }}</td>
                        <td class="px-5 py-3">{{ $cot->detalles_count }}</td>
                        <td class="px-5 py-3">{{ \App\Support\Moneda::cop($cot->total) }}</td>
                        <td class="px-5 py-3">
                            <span class="inline-block px-2.5 py-1 rounded-full text-[11.5px] font-semibold bg-brand-blue-tint text-brand-blue dark:bg-brand-navy-active dark:text-white">
                                {{ ucfirst(str_replace('_', ' ', $cot->estado)) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('cotizaciones.gestionar', $cot) }}" class="text-xs font-semibold text-brand-blue hover:underline">Abrir</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">Sin cotizaciones registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $cotizaciones->links() }}
</div>
