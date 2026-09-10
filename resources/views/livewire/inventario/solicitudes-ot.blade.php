<?php

use App\Livewire\Concerns\Notifies;
use App\Models\Inventario;
use App\Models\SolicitudInsumoOt;
use App\Services\OrdenTrabajo\AtencionInsumoOtService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layout', ['title' => 'Insumos para OT'])] class extends Component
{
    use Notifies, WithPagination;

    #[Url]
    public string $estado = 'pendiente';

    public ?int $rechazandoId = null;
    public string $motivoRechazo = '';

    public function mount(): void
    {
        Gate::authorize('attend-ot-insumo');
    }

    public function updatingEstado(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $q = SolicitudInsumoOt::query()
            ->with(['ordenTrabajo:id,numero_ot,cliente_id', 'ordenTrabajo.cliente:id,nombre', 'tarea:id,descripcion,tecnico_id', 'tarea.tecnico.usuario:id,name', 'entregadoATecnico.usuario:id,name', 'inventario:id,nombre,codigo,tipo,stock_actual', 'movimiento:id'])
            ->when($this->estado !== 'todas', fn ($q) => $q->where('estado', $this->estado))
            ->latest('id');

        return [
            'solicitudes' => $q->paginate(12),
            'conteos' => SolicitudInsumoOt::selectRaw('estado, count(*) c')->groupBy('estado')->pluck('c', 'estado'),
        ];
    }

    public function entregar(int $id, AtencionInsumoOtService $svc): void
    {
        Gate::authorize('attend-ot-insumo');
        try {
            $svc->entregar(SolicitudInsumoOt::findOrFail($id), auth()->user());
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }
        $this->notifySuccess('Insumo entregado y descontado del stock.');
    }

    public function pedirRechazo(int $id): void
    {
        $this->rechazandoId = $id;
        $this->motivoRechazo = '';
    }

    public function rechazar(AtencionInsumoOtService $svc): void
    {
        Gate::authorize('attend-ot-insumo');
        $this->validate(['motivoRechazo' => 'required|string|max:500'], [], ['motivoRechazo' => 'motivo']);
        try {
            $svc->rechazar(SolicitudInsumoOt::findOrFail($this->rechazandoId), auth()->user(), $this->motivoRechazo);
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }
        $this->rechazandoId = null;
        $this->motivoRechazo = '';
        $this->notifySuccess('Solicitud rechazada.');
    }
}; ?>

<div class="w-full flex flex-col gap-5">
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-lg font-bold font-display">Insumos para OT</h1>
        <p class="text-sm text-slate-400">Solicitudes de insumo generadas por las tareas de las órdenes de trabajo.</p>
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach (['pendiente' => 'Pendientes', 'entregada' => 'Entregadas', 'rechazada' => 'Rechazadas', 'cancelada' => 'Canceladas', 'todas' => 'Todas'] as $k => $label)
            <button wire:click="$set('estado', '{{ $k }}')"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[12.5px] font-semibold transition
                           {{ $estado === $k ? 'bg-brand-blue text-white' : 'border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                {{ $label }}
                @if ($k !== 'todas' && ($conteos[$k] ?? 0) > 0)
                    <span class="rounded-full bg-white/20 {{ $estado === $k ? '' : 'bg-slate-100 dark:bg-slate-800' }} px-1.5 text-[10px]">{{ $conteos[$k] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                <tr>
                    <th class="px-4 py-3 font-semibold">OT</th>
                    <th class="px-4 py-3 font-semibold">Tarea</th>
                    <th class="px-4 py-3 font-semibold">Entregar a</th>
                    <th class="px-4 py-3 font-semibold">Insumo</th>
                    <th class="px-4 py-3 font-semibold text-right">Solicitado</th>
                    <th class="px-4 py-3 font-semibold text-right">Stock</th>
                    <th class="px-4 py-3 font-semibold">Estado</th>
                    <th class="px-4 py-3 font-semibold">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($solicitudes as $s)
                    @php
                        $nfmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
                        $esConsumible = $s->inventario && $s->inventario->tipo === 'consumible';
                        $falta = $s->estado === 'pendiente' && $esConsumible && (float) $s->inventario->stock_actual < (float) $s->cantidad;
                        $disp = $esConsumible ? $s->inventario->disponible() : null;
                    @endphp
                    <tr wire:key="sol-{{ $s->id }}" class="border-b border-slate-50 dark:border-slate-800/60 align-top">
                        <td class="px-4 py-3">
                            <a href="{{ route('ordenes-trabajo.detalle', $s->ot_id) }}" wire:navigate class="font-semibold text-brand-blue hover:underline">{{ $s->ordenTrabajo?->numero_ot }}</a>
                            <div class="text-xs text-slate-400">{{ $s->ordenTrabajo?->cliente?->nombre }}</div>
                        </td>
                        <td class="px-4 py-3 max-w-[16rem] text-slate-500 dark:text-slate-400">{{ $s->tarea?->descripcion }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $s->entregadoATecnico?->usuario?->name ?? $s->tarea?->tecnico?->usuario?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $s->inventario?->nombre }} <span class="text-xs text-slate-400">({{ $s->inventario?->codigo }})</span></td>
                        <td class="px-4 py-3 text-right">{{ $nfmt($s->cantidad) }}</td>
                        <td class="px-4 py-3 text-right {{ $falta ? 'text-brand-red font-semibold' : '' }}">
                            {{ $nfmt($s->inventario?->stock_actual ?? 0) }}
                            @if ($disp !== null)
                                <div class="text-[10px] font-normal {{ $disp < (float) $s->cantidad ? 'text-brand-red' : 'text-slate-400' }}">disp. {{ $nfmt($disp) }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold
                                {{ $s->estado === 'entregada' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : ($s->estado === 'rechazada' ? 'bg-red-100 text-brand-red dark:bg-red-900/30' : 'bg-slate-100 dark:bg-slate-800') }}">
                                {{ ucfirst($s->estado) }}
                            </span>
                            @if ($s->estado === 'rechazada' && $s->motivo_rechazo)
                                <div class="text-xs text-brand-red mt-1">{{ $s->motivo_rechazo }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($s->estado === 'pendiente')
                                @if ($rechazandoId === $s->id)
                                    <div class="flex flex-col gap-2 w-56">
                                        <input type="text" wire:model="motivoRechazo" placeholder="Motivo del rechazo" class="h-9 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 px-3 text-xs outline-none focus:border-brand-blue focus:ring-4 focus:ring-brand-blue/10">
                                        <div class="flex gap-2">
                                            <button wire:click="rechazar" class="text-[12px] font-semibold px-3 py-1.5 rounded-lg border border-brand-red text-brand-red">Confirmar rechazo</button>
                                            <button wire:click="$set('rechazandoId', null)" class="text-[12px] px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700">Cancelar</button>
                                        </div>
                                        @error('motivoRechazo') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                                    </div>
                                @else
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button"
                                                x-on:click="Notify.confirmDanger({
                                                    title: '¿Entregar el insumo?',
                                                    text: {{ Js::from('Se descontarán '.$nfmt($s->cantidad).' uds. de «'.$s->inventario?->nombre.'» del stock. Esta acción no se puede deshacer.') }},
                                                    confirmButtonText: 'Sí, entregar',
                                                }).then((ok) => ok && $wire.entregar({{ $s->id }}))"
                                                class="text-[12px] font-semibold px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Entregar</button>
                                        <button wire:click="pedirRechazo({{ $s->id }})" class="text-[12px] px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-400 hover:text-brand-red">Rechazar</button>
                                    </div>
                                @endif
                            @elseif ($s->estado === 'entregada')
                                <span class="text-xs text-slate-400">Movimiento #{{ $s->movimiento_id }}</span>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Sin solicitudes en este estado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $solicitudes->links() }}</div>
</div>
