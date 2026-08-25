<?php

use App\Models\AjusteAuditoria;
use App\Models\AuditoriaInventario;
use App\Models\CategoriaInventario;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Support\Moneda;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Inventario'])] class extends Component
{
    public function mount(): void
    {
        Gate::authorize('viewAny', Inventario::class);
    }

    public function with(): array
    {
        $items = Inventario::activos()->get(['id', 'tipo', 'categoria_id', 'stock_actual', 'stock_minimo', 'estado_herramienta']);

        // El valor real de cada ítem es la suma de sus lotes de entrada con saldo
        // disponible (ver Inventario::valorTotal()) — no stock_actual × un costo
        // único, que no reflejaría compras a precios distintos.
        $valoresPorItem = MovimientoInventario::where('tipo_mov', 'entrada')
            ->where('cantidad_disponible', '>', 0)
            ->whereIn('inventario_id', $items->pluck('id'))
            ->get(['inventario_id', 'cantidad_disponible', 'costo_unitario'])
            ->groupBy('inventario_id')
            ->map(fn ($lotes) => $lotes->sum(
                fn (MovimientoInventario $lote) => (float) $lote->cantidad_disponible * (float) ($lote->costo_unitario ?? 0)
            ));

        $valorTotal = $valoresPorItem->sum();

        $bajoMinimo = $items->filter(fn (Inventario $i) => $i->tipo === 'consumible' && (float) $i->stock_actual < (float) $i->stock_minimo);

        $porCategoria = CategoriaInventario::activas()
            ->withCount(['items' => fn ($q) => $q->activos()])
            ->orderBy('nombre')
            ->get()
            ->map(function (CategoriaInventario $categoria) use ($items, $valoresPorItem) {
                $valor = $items->where('categoria_id', $categoria->id)
                    ->sum(fn (Inventario $i) => $valoresPorItem->get($i->id, 0));

                return ['categoria' => $categoria, 'valor' => $valor];
            });

        return [
            'totalItems' => $items->count(),
            'valorTotal' => $valorTotal,
            'itemsBajoMinimo' => $bajoMinimo->count(),
            'topBajoMinimo' => Inventario::activos()->where('tipo', 'consumible')
                ->whereColumn('stock_actual', '<', 'stock_minimo')
                ->orderBy('stock_actual')
                ->take(6)
                ->get(),
            'herramientasDisponibles' => $items->where('tipo', 'herramienta')->where('estado_herramienta', 'disponible')->count(),
            'herramientasEnUso' => $items->where('tipo', 'herramienta')->where('estado_herramienta', 'en_uso')->count(),
            'herramientasDanadas' => $items->whereIn('estado_herramienta', ['dañada', 'en_mantenimiento'])->count(),
            'porCategoria' => $porCategoria,
            'auditoriaAbierta' => AuditoriaInventario::abiertas()->latest('fecha_inicio')->first(),
            'ajustesPendientesCount' => AjusteAuditoria::where('estado', 'pendiente')->count(),
        ];
    }
}; ?>

<div>
    @include('partials.inventario-tabs')

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5">
            <div class="text-[13px] text-slate-500 dark:text-slate-400 mb-2">Ítems activos</div>
            <div class="text-3xl font-bold">{{ $totalItems }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5">
            <div class="text-[13px] text-slate-500 dark:text-slate-400 mb-2">Valor real del inventario</div>
            <div class="text-3xl font-bold">{{ Moneda::cop($valorTotal) }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-[13px] text-slate-500 dark:text-slate-400 mb-2">Bajo stock mínimo</div>
                    <div class="text-3xl font-bold {{ $itemsBajoMinimo > 0 ? 'text-brand-red' : '' }}">{{ $itemsBajoMinimo }}</div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-brand-red-tint flex items-center justify-center shrink-0">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#e0332c" stroke-width="1.9"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L2.6 17.5A1.6 1.6 0 004 20h16a1.6 1.6 0 001.4-2.5L13.7 3.9a1.6 1.6 0 00-3.4 0z"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5">
            <div class="text-[13px] text-slate-500 dark:text-slate-400 mb-2">Herramientas</div>
            <div class="flex items-baseline gap-3">
                <span class="text-2xl font-bold text-emerald-600">{{ $herramientasDisponibles }}</span>
                <span class="text-xs text-slate-400">disponibles</span>
            </div>
            <div class="flex items-baseline gap-3 mt-1">
                <span class="text-2xl font-bold text-brand-blue">{{ $herramientasEnUso }}</span>
                <span class="text-xs text-slate-400">en uso</span>
            </div>
            @if ($herramientasDanadas > 0)
                <div class="flex items-baseline gap-3 mt-1">
                    <span class="text-2xl font-bold text-brand-red">{{ $herramientasDanadas }}</span>
                    <span class="text-xs text-slate-400">dañadas / en mantenimiento</span>
                </div>
            @endif
        </div>
    </div>

    @if ($auditoriaAbierta || $ajustesPendientesCount > 0)
        <div class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-900 rounded-2xl p-4 mb-6 flex items-center justify-between flex-wrap gap-2">
            <p class="text-sm text-amber-800 dark:text-amber-400">
                @if ($auditoriaAbierta)
                    Hay una auditoría en curso, iniciada el {{ $auditoriaAbierta->fecha_inicio->format('d/m/Y') }}.
                @endif
                @if ($ajustesPendientesCount > 0)
                    {{ $ajustesPendientesCount }} ajuste(s) de auditoría esperando aprobación.
                @endif
            </p>
            <a href="{{ route('inventario.auditoria') }}" class="text-sm font-semibold text-amber-800 dark:text-amber-400 hover:underline">Ir a Auditoría →</a>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 font-bold text-sm">Ítems más críticos por stock bajo</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                            <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Ítem</th>
                            <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Stock</th>
                            <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Mínimo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topBajoMinimo as $item)
                            <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                                <td class="px-5 py-3 whitespace-nowrap">{{ $item->nombre }}</td>
                                <td class="px-5 py-3 font-semibold text-brand-red whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $item->stock_actual, 2), '0'), '.') }}</td>
                                <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $item->stock_minimo, 2), '0'), '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-8 text-center text-slate-400">Ningún ítem está bajo su stock mínimo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 font-bold text-sm">Valor por categoría</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                            <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Categoría</th>
                            <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Ítems</th>
                            <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($porCategoria as $fila)
                            <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                                <td class="px-5 py-3 whitespace-nowrap">{{ $fila['categoria']->nombre }}</td>
                                <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $fila['categoria']->items_count }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">{{ Moneda::cop($fila['valor']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-8 text-center text-slate-400">Sin categorías registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
