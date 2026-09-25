<?php

use App\Models\OtEvento;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/**
 * Actividad reciente sobre Órdenes de Trabajo, unificada across todas las OT
 * (hoy `ot_eventos` solo se consulta por OT individual, dentro del detalle).
 * Reutiliza la bitácora append-only que ya existe (spec 002) — sin tabla
 * nueva; cubre creación, cambios de estado, correcciones, salidas, entregas
 * e insumos, no inventario ni usuarios (esos no tienen bitácora hoy).
 */
new #[Layout('components.layout', ['title' => 'Auditoría'])] class extends Component
{
    use WithPagination;

    #[Url]
    public string $tipo = '';

    #[Url]
    public string $usuario = '';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view-auditoria'), 403);
    }

    public function updating($campo): void
    {
        if (in_array($campo, ['tipo', 'usuario', 'desde', 'hasta'], true)) {
            $this->resetPage();
        }
    }

    public function limpiar(): void
    {
        $this->reset('tipo', 'usuario', 'desde', 'hasta');
        $this->resetPage();
    }

    public function with(): array
    {
        $eventos = OtEvento::query()
            ->with(['ordenTrabajo:id,numero_ot', 'usuario:id,name'])
            ->when($this->tipo !== '', fn ($q) => $q->where('tipo', $this->tipo))
            ->when($this->usuario !== '', fn ($q) => $q->where('usuario_id', $this->usuario))
            ->when($this->desde !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->desde))
            ->when($this->hasta !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->hasta))
            ->latest('created_at')
            ->paginate(25);

        return [
            'eventos' => $eventos,
            'tipos' => OtEvento::query()->distinct()->orderBy('tipo')->pluck('tipo'),
            'usuarios' => User::whereIn('id', OtEvento::query()->whereNotNull('usuario_id')->distinct()->pluck('usuario_id'))
                ->orderBy('name')->get(['id', 'name']),
        ];
    }
}; ?>

<div class="flex flex-col gap-5">
    <x-breadcrumbs :items="[['label' => 'Auditoría']]" />

    <div>
        <h1 class="text-lg font-bold">Auditoría</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Actividad reciente sobre Órdenes de Trabajo: quién hizo qué y cuándo.</p>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 grid grid-cols-1 sm:grid-cols-4 gap-3 text-sm">
        <select wire:model.live="tipo" class="border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
            <option value="">Todos los tipos</option>
            @foreach ($tipos as $t)
                <option value="{{ $t }}">{{ str($t)->replace('_', ' ')->ucfirst() }}</option>
            @endforeach
        </select>
        <select wire:model.live="usuario" class="border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
            <option value="">Todos los usuarios</option>
            @foreach ($usuarios as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
        </select>
        <input type="date" wire:model.live="desde" title="Desde" class="border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
        <input type="date" wire:model.live="hasta" title="Hasta" class="border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                <tr>
                    <th class="px-4 py-3 font-semibold">Fecha</th>
                    <th class="px-4 py-3 font-semibold">OT</th>
                    <th class="px-4 py-3 font-semibold">Tipo</th>
                    <th class="px-4 py-3 font-semibold">Descripción</th>
                    <th class="px-4 py-3 font-semibold">Usuario</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($eventos as $evento)
                    <tr class="border-b border-slate-50 dark:border-slate-800/60 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 text-slate-400 whitespace-nowrap">{{ $evento->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            @if ($evento->ordenTrabajo)
                                <a href="{{ route('ordenes-trabajo.detalle', $evento->ordenTrabajo) }}" wire:navigate class="font-semibold text-brand-blue hover:underline">{{ $evento->ordenTrabajo->numero_ot }}</a>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-0.5 text-xs font-semibold">{{ str($evento->tipo)->replace('_', ' ')->ucfirst() }}</span>
                        </td>
                        <td class="px-4 py-3 max-w-[26rem] truncate text-slate-500 dark:text-slate-400">{{ $evento->descripcion }}</td>
                        <td class="px-4 py-3">{{ $evento->usuario?->name ?? 'Sistema' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Sin eventos que coincidan.
                        @if ($tipo || $usuario || $desde || $hasta)
                            <button wire:click="limpiar" class="text-brand-blue hover:underline ml-1">Limpiar filtros</button>
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $eventos->links() }}</div>
</div>
