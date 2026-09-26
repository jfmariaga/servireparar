<?php

use App\Livewire\Concerns\Notifies;
use App\Models\Compra;
use App\Services\Compras\CompraService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Compra'])] class extends Component
{
    use Notifies;

    public Compra $compra;

    public function mount(Compra $compra): void
    {
        Gate::authorize('view', $compra);
        $this->compra = $compra;
    }

    public function with(): array
    {
        $this->compra->load(['proveedor', 'detalles.inventario', 'creadoPor']);

        return [];
    }

    public function marcarCotizacion(): void
    {
        app(CompraService::class)->marcarCotizacion($this->compra);
        $this->compra->refresh();
        $this->notifySuccess('Compra pasó a cotización.');
    }

    public function aprobar(): void
    {
        app(CompraService::class)->aprobar($this->compra);
        $this->compra->refresh();
        $this->notifySuccess('Compra aprobada.');
    }

    public function facturar(): void
    {
        app(CompraService::class)->facturar($this->compra);
        $this->compra->refresh();
        $this->notifySuccess('Compra marcada como facturada.');
    }
}; ?>

<div class="flex flex-col gap-6 max-w-3xl">
    <x-breadcrumbs :items="[['label' => 'Compras', 'route' => 'compras.tablero'], ['label' => $compra->numero]]" />

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Solicitud de compra</p>
            <p class="text-xl font-bold">{{ $compra->numero }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $compra->proveedor->nombre }} · creada por {{ $compra->creadoPor->name }}</p>
            @if ($compra->observaciones)
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $compra->observaciones }}</p>
            @endif
        </div>
        <div class="text-right">
            <span class="inline-block px-3 py-1.5 rounded-full text-xs font-semibold bg-brand-blue-tint text-brand-blue dark:bg-brand-navy-active dark:text-white">
                {{ ucfirst($compra->estado) }}
            </span>
            <p class="text-lg font-bold mt-1">{{ \App\Support\Moneda::cop($compra->total) }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Ítem</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Cantidad</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Costo unit.</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($compra->detalles as $d)
                    <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                        <td class="px-5 py-3">{{ $d->inventario->nombre }}</td>
                        <td class="px-5 py-3">{{ rtrim(rtrim(number_format((float) $d->cantidad, 2), '0'), '.') }}</td>
                        <td class="px-5 py-3">{{ \App\Support\Moneda::cop($d->costo_unitario) }}</td>
                        <td class="px-5 py-3 font-semibold">{{ \App\Support\Moneda::cop($d->valor_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-wrap gap-3">
        @if ($compra->estado === 'recepcion')
            <button wire:click="marcarCotizacion" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Pasar a cotización
            </button>
        @endif
        @if ($compra->estado === 'cotizacion')
            <button wire:click="aprobar" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Aprobar
            </button>
        @endif
        @if ($compra->estado === 'aprobacion')
            <button wire:click="facturar" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Marcar facturada
            </button>
        @endif
        @if ($compra->estado === 'facturada')
            <p class="text-sm text-slate-500 dark:text-slate-400">Compra facturada el {{ $compra->facturada_en?->format('d/m/Y') }}.</p>
        @endif
    </div>
</div>
