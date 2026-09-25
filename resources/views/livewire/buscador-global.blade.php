<?php

use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

/**
 * Buscador global (Cmd+K / Ctrl+K): salta directo a una OT, cliente o ítem de
 * inventario sin navegar menús. Cada categoría respeta el mismo permiso que
 * ya protege su listado (nadie ve por aquí algo que no vería en su pantalla).
 */
new class extends Component
{
    public bool $abierto = false;

    public string $q = '';

    #[On('abrir-buscador')]
    public function abrir(): void
    {
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->q = '';
    }

    public function with(): array
    {
        $termino = trim($this->q);

        if (! $this->abierto || mb_strlen($termino) < 2) {
            return ['ots' => collect(), 'clientes' => collect(), 'items' => collect()];
        }

        return [
            'ots' => OrdenTrabajo::query()
                ->visiblesPara(auth()->user())
                ->buscar($termino)
                ->with('cliente:id,nombre')
                ->latest('id')->limit(5)->get(),
            'clientes' => Gate::allows('viewAny', Cliente::class)
                ? Cliente::query()->buscar($termino)->orderBy('nombre')->limit(5)->get()
                : collect(),
            'items' => Gate::allows('viewAny', Inventario::class)
                ? Inventario::query()->buscar($termino)->orderBy('nombre')->limit(5)->get()
                : collect(),
        ];
    }
}; ?>

<div x-data @keydown.window.prevent.meta.k="$wire.abrir()" @keydown.window.prevent.ctrl.k="$wire.abrir()">
    <button @click="$wire.abrir()" title="Buscar (Ctrl/Cmd+K)"
            class="w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
    </button>

    @if ($abierto)
        <div class="fixed inset-0 z-50 flex items-start justify-center pt-24 p-4" @keydown.escape.window="$wire.cerrar()">
            <div class="absolute inset-0 bg-black/40" wire:click="cerrar"></div>

            <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl w-full max-w-xl max-h-[70vh] flex flex-col">
                <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-100 dark:border-slate-800 shrink-0">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="text-slate-400 shrink-0"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
                    <input type="text" wire:model.live.debounce.250ms="q" x-init="$nextTick(() => $el.focus())" autofocus
                           placeholder="Buscar OT, cliente o ítem de inventario..."
                           class="flex-1 border-0 outline-none bg-transparent text-sm dark:text-slate-100">
                    <button wire:click="cerrar" class="shrink-0 text-[11px] text-slate-400 border border-slate-200 dark:border-slate-700 rounded px-1.5 py-0.5">Esc</button>
                </div>

                <div class="overflow-y-auto px-2 py-2">
                    @if (mb_strlen(trim($q)) < 2)
                        <p class="text-sm text-slate-400 text-center py-8">Escribe al menos 2 letras para buscar.</p>
                    @elseif ($ots->isEmpty() && $clientes->isEmpty() && $items->isEmpty())
                        <p class="text-sm text-slate-400 text-center py-8">Sin resultados para «{{ $q }}».</p>
                    @else
                        @if ($ots->isNotEmpty())
                            <p class="px-3 pt-2 pb-1 text-[10px] font-bold uppercase tracking-wide text-slate-400">Órdenes de trabajo</p>
                            @foreach ($ots as $ot)
                                <a href="{{ route('ordenes-trabajo.detalle', $ot) }}" wire:navigate wire:click="cerrar"
                                   class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                                    <span>
                                        <span class="font-semibold text-brand-blue">{{ $ot->numero_ot }}</span>
                                        <span class="text-slate-400">· {{ $ot->cliente?->nombre }} · {{ \Illuminate\Support\Str::limit($ot->descripcion, 40) }}</span>
                                    </span>
                                </a>
                            @endforeach
                        @endif

                        @if ($clientes->isNotEmpty())
                            <p class="px-3 pt-2 pb-1 text-[10px] font-bold uppercase tracking-wide text-slate-400">Clientes</p>
                            @foreach ($clientes as $cliente)
                                <a href="{{ route('clientes.index') }}" wire:navigate wire:click="cerrar"
                                   class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                                    <span>{{ $cliente->nombre }} <span class="text-slate-400">· {{ $cliente->nit ?: 'sin NIT' }}</span></span>
                                </a>
                            @endforeach
                        @endif

                        @if ($items->isNotEmpty())
                            <p class="px-3 pt-2 pb-1 text-[10px] font-bold uppercase tracking-wide text-slate-400">Inventario</p>
                            @foreach ($items as $item)
                                <a href="{{ route('inventario.catalogo') }}" wire:navigate wire:click="cerrar"
                                   class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                                    <span>{{ $item->nombre }} <span class="text-slate-400">· {{ $item->codigo }}</span></span>
                                </a>
                            @endforeach
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
