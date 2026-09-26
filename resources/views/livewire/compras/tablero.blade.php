<?php

use App\Models\Compra;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layout', ['title' => 'Compras'])] class extends Component
{
    use WithPagination;

    #[Url]
    public string $estado = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Compra::class);
    }

    public function with(): array
    {
        $compras = Compra::query()
            ->with('proveedor')
            ->withCount('detalles')
            ->when($this->estado !== '', fn ($q) => $q->where('estado', $this->estado))
            ->orderByDesc('created_at')
            ->paginate(12);

        return [
            'compras' => $compras,
            'estados' => ['recepcion', 'cotizacion', 'aprobacion', 'facturada'],
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-breadcrumbs :items="[['label' => 'Compras']]" />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <label class="text-sm font-semibold text-slate-500 dark:text-slate-400">Estado</label>
            <x-select wire:model.live="estado" :placeholder="'Todos'">
                @foreach ($estados as $e)
                    <option value="{{ $e }}">{{ ucfirst($e) }}</option>
                @endforeach
            </x-select>
        </div>
        <a href="{{ route('compras.nueva') }}" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
            Nueva solicitud de compra
        </a>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Número</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Proveedor</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Ítems</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Total</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Estado</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($compras as $compra)
                    <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-5 py-3 font-mono font-semibold">{{ $compra->numero }}</td>
                        <td class="px-5 py-3">{{ $compra->proveedor->nombre }}</td>
                        <td class="px-5 py-3">{{ $compra->detalles_count }}</td>
                        <td class="px-5 py-3">{{ \App\Support\Moneda::cop($compra->total) }}</td>
                        <td class="px-5 py-3">
                            <span class="inline-block px-2.5 py-1 rounded-full text-[11.5px] font-semibold bg-brand-blue-tint text-brand-blue dark:bg-brand-navy-active dark:text-white">
                                {{ ucfirst($compra->estado) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('compras.gestionar', $compra) }}" class="text-xs font-semibold text-brand-blue hover:underline">Abrir</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">Sin solicitudes de compra registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $compras->links() }}
</div>
