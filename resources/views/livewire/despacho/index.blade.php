<?php

use App\Models\SolicitudDespacho;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layout', ['title' => 'Despachos'])] class extends Component
{
    use WithPagination;

    #[Url]
    public string $estado = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', SolicitudDespacho::class);
    }

    public function with(): array
    {
        $esVendedor = auth()->user()->hasRole('Vendedor') && ! auth()->user()->hasAnyRole(['Almacenista', 'Administrador']);
        $esAlmacen = auth()->user()->hasAnyRole(['Almacenista', 'Administrador']);

        $solicitudes = SolicitudDespacho::query()
            ->with(['cliente', 'vendedor'])
            ->withCount([
                'detalles',
                'detalles as compra_externa_count' => fn ($q) => $q->where('origen', 'compra_externa'),
            ])
            ->when($esVendedor, fn ($q) => $q->where('vendedor_id', auth()->id()))
            ->when($this->estado !== '', fn ($q) => $q->where('estado', $this->estado))
            ->orderByDesc('fecha_solicitud')
            ->paginate(12);

        return [
            'solicitudes' => $solicitudes,
            'puedeCrear' => auth()->user()->can('create', SolicitudDespacho::class),
            'estados' => ['solicitada', 'recibida', 'remisionada', 'entregada', 'anulada'],
            'pendientesAlmacen' => $esAlmacen
                ? SolicitudDespacho::whereIn('estado', ['solicitada', 'recibida', 'remisionada'])->count()
                : null,
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    @if ($pendientesAlmacen)
        <div class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-sm rounded-xl px-4 py-3">
            Tienes <strong>{{ $pendientesAlmacen }}</strong> {{ $pendientesAlmacen === 1 ? 'solicitud pendiente' : 'solicitudes pendientes' }} por gestionar (recibir, remisionar o entregar).
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <label class="text-sm font-semibold text-slate-500 dark:text-slate-400">Estado</label>
            <div class="w-48">
                <x-select wire:model.live="estado">
                    <option value="">Todos</option>
                    @foreach ($estados as $e)
                        <option value="{{ $e }}">{{ ucfirst($e) }}</option>
                    @endforeach
                </x-select>
            </div>
        </div>
        @if ($puedeCrear)
            <a href="{{ route('despachos.nueva') }}"
               class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Nueva solicitud
            </a>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Número</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Ciudad</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Fecha</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Cliente</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Vendedor</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Líneas</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Estado</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($solicitudes as $s)
                        <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                            <td class="px-5 py-3 font-mono text-[12.5px] whitespace-nowrap">{{ $s->numero }}</td>
                            <td class="px-5 py-3 font-mono text-[12.5px] whitespace-nowrap">{{ $s->sede }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $s->fecha_solicitud->format('d/m/y') }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">{{ $s->cliente->nombre }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $s->vendedor->name }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                {{ $s->detalles_count }}
                                @if ($s->compra_externa_count > 0)
                                    <span class="ml-1.5 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">{{ $s->compra_externa_count }} ext.</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wide',
                                    'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => in_array($s->estado, ['solicitada', 'recibida']),
                                    'bg-brand-blue-tint text-brand-blue' => $s->estado === 'remisionada',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $s->estado === 'entregada',
                                    'bg-brand-red-tint text-brand-red' => $s->estado === 'anulada',
                                ])>{{ $s->estado }}</span>
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('despachos.detalle', $s) }}" class="text-xs font-semibold text-brand-blue hover:underline">Abrir</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-10 text-center text-slate-400">Sin solicitudes de despacho.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3.5">{{ $solicitudes->links() }}</div>
    </div>
</div>
