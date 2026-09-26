<?php

use App\Livewire\Concerns\Notifies;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Inventario;
use App\Models\Servicio;
use App\Services\Cotizaciones\CotizacionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Cotización'])] class extends Component
{
    use Notifies;

    public Cotizacion $cotizacion;

    public ?int $clienteIdAsignar = null;

    /** @var array<int, array{uid: string, tipo_item: string, servicio_id: ?int, inventario_id: ?int, cantidad: float}> */
    public array $items = [];

    public string $comentario = '';

    public function mount(Cotizacion $cotizacion): void
    {
        Gate::authorize('view', $cotizacion);
        $this->cotizacion = $cotizacion;
        $this->cargarItemsDesdeDetalle();
    }

    public function with(): array
    {
        $this->cotizacion->load(['cliente', 'detalles.servicio', 'detalles.inventario', 'mensajes.autor']);

        return [
            'clientes' => Cliente::activos()->orderBy('nombre')->get(),
            'servicios' => Servicio::activos()->orderBy('nombre')->get(),
            'insumos' => Inventario::where('activo', true)->orderBy('nombre')->get(),
            'puedeEditar' => in_array($this->cotizacion->estado, ['en_revision'], true),
        ];
    }

    private function cargarItemsDesdeDetalle(): void
    {
        $this->items = $this->cotizacion->detalles->map(fn ($d) => [
            'uid' => (string) Str::uuid(),
            'tipo_item' => $d->tipo_item,
            'servicio_id' => $d->servicio_id,
            'inventario_id' => $d->inventario_id,
            'cantidad' => (float) $d->cantidad,
        ])->all();
    }

    public function agregarItem(): void
    {
        $this->items[] = [
            'uid' => (string) Str::uuid(),
            'tipo_item' => 'servicio',
            'servicio_id' => null,
            'inventario_id' => null,
            'cantidad' => 1,
        ];
    }

    public function quitarItem(int $i): void
    {
        unset($this->items[$i]);
        $this->items = array_values($this->items);
    }

    public function guardarItems(): void
    {
        $datos = $this->validate([
            'items' => 'required|array|min:1',
            'items.*.tipo_item' => 'required|in:servicio,insumo',
            'items.*.servicio_id' => 'nullable|exists:servicios,id',
            'items.*.inventario_id' => 'nullable|exists:inventario,id',
            'items.*.cantidad' => 'required|numeric|min:0.01',
        ], [], ['items' => 'ítems']);

        app(CotizacionService::class)->guardarItems($this->cotizacion, $datos['items']);
        $this->cotizacion->refresh();
        $this->cargarItemsDesdeDetalle();
        $this->notifySuccess('Ítems guardados. Total: '.\App\Support\Moneda::cop($this->cotizacion->total));
    }

    public function asignarCliente(): void
    {
        $this->validate(['clienteIdAsignar' => 'required|exists:clientes,id']);

        app(CotizacionService::class)->asignarCliente($this->cotizacion, Cliente::findOrFail($this->clienteIdAsignar));
        $this->cotizacion->refresh();
        $this->notifySuccess('Cliente asignado a la cotización.');
    }

    public function enviar(): void
    {
        app(CotizacionService::class)->enviar($this->cotizacion, auth()->user());
        $this->cotizacion->refresh();
        $this->notifySuccess('Cotización enviada al cliente.');
    }

    public function marcarEntregada(): void
    {
        app(CotizacionService::class)->marcarEntregada($this->cotizacion);
        $this->cotizacion->refresh();
        $this->notifySuccess('Cotización marcada como entregada.');
    }

    public function marcarFacturada(): void
    {
        app(CotizacionService::class)->marcarFacturada($this->cotizacion);
        $this->cotizacion->refresh();
        $this->notifySuccess('Cotización marcada como facturada.');
    }

    public function agregarComentario(): void
    {
        $this->validate(['comentario' => 'required|string|max:1000']);

        $this->cotizacion->mensajes()->create([
            'autor_tipo' => 'administrador',
            'autor_id' => auth()->id(),
            'contenido' => $this->comentario,
        ]);

        $this->comentario = '';
        $this->notifySuccess('Comentario agregado.');
    }
}; ?>

<div class="flex flex-col gap-6 max-w-4xl">
    <x-breadcrumbs :items="[['label' => 'Cotizaciones', 'route' => 'cotizaciones.tablero'], ['label' => $cotizacion->numero]]" />

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Cotización</p>
            <p class="text-xl font-bold">{{ $cotizacion->numero }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $cotizacion->cliente?->nombre ?? 'Sin cliente identificado' }}</p>
        </div>
        <div class="text-right">
            <span class="inline-block px-3 py-1.5 rounded-full text-xs font-semibold bg-brand-blue-tint text-brand-blue dark:bg-brand-navy-active dark:text-white">
                {{ ucfirst(str_replace('_', ' ', $cotizacion->estado)) }}
            </span>
            <p class="text-lg font-bold mt-1">{{ \App\Support\Moneda::cop($cotizacion->total) }}</p>
        </div>
    </div>

    @if (! $cotizacion->cliente)
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-3">
            <h2 class="font-bold text-sm">Asignar cliente</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">El remitente de este correo no coincide con ningún cliente registrado. Asígnalo manualmente para poder enviar la cotización.</p>
            <div class="flex items-end gap-3 max-w-md">
                <div class="flex-1">
                    <x-select wire:model="clienteIdAsignar">
                        @foreach ($clientes as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                        @endforeach
                    </x-select>
                    @error('clienteIdAsignar') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <button wire:click="asignarCliente" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">Asignar</button>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-sm">Ítems cotizados</h2>
            @if ($puedeEditar)
                <button wire:click="agregarItem" type="button" class="text-xs font-semibold text-brand-blue hover:underline">+ Agregar ítem</button>
            @endif
        </div>
        @error('items') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror

        @if ($puedeEditar)
            @foreach ($items as $i => $item)
                <div wire:key="item-{{ $item['uid'] }}" class="border border-slate-200 dark:border-slate-800 rounded-xl p-4 flex flex-col gap-3">
                    <div class="flex items-center gap-4 text-sm">
                        <label class="flex items-center gap-1.5">
                            <input type="radio" value="servicio" wire:model.live="items.{{ $i }}.tipo_item"> Servicio
                        </label>
                        <label class="flex items-center gap-1.5">
                            <input type="radio" value="insumo" wire:model.live="items.{{ $i }}.tipo_item"> Insumo
                        </label>
                        <button wire:click="quitarItem({{ $i }})" type="button" class="ml-auto text-xs text-brand-red hover:underline">Quitar</button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                        <div class="sm:col-span-2">
                            @if (($item['tipo_item'] ?? 'servicio') === 'servicio')
                                <x-select wire:model="items.{{ $i }}.servicio_id" :reset-key="'srv-'.$item['uid']">
                                    @foreach ($servicios as $s)
                                        <option value="{{ $s->id }}">{{ $s->nombre }} — {{ \App\Support\Moneda::cop($s->costo_unitario) }}</option>
                                    @endforeach
                                </x-select>
                            @else
                                <x-select wire:model="items.{{ $i }}.inventario_id" :reset-key="'inv-'.$item['uid']">
                                    @foreach ($insumos as $ins)
                                        <option value="{{ $ins->id }}">{{ $ins->nombre }} — {{ \App\Support\Moneda::cop($ins->costo_unitario) }}</option>
                                    @endforeach
                                </x-select>
                            @endif
                        </div>
                        <div>
                            <input type="number" step="0.01" min="0.01" wire:model="items.{{ $i }}.cantidad" placeholder="Cantidad" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                        </div>
                    </div>
                </div>
            @endforeach

            <button wire:click="guardarItems" class="self-start bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Guardar ítems
            </button>
        @else
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($cotizacion->detalles as $d)
                        <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                            <td class="py-2">{{ $d->nombreItem() }}</td>
                            <td class="py-2 text-right">{{ rtrim(rtrim(number_format((float) $d->cantidad, 2), '0'), '.') }} × {{ \App\Support\Moneda::cop($d->costo_unitario) }}</td>
                            <td class="py-2 text-right font-semibold">{{ \App\Support\Moneda::cop($d->valor_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-wrap gap-3">
        @if ($cotizacion->estado === 'en_revision')
            <button wire:click="enviar" wire:confirm="¿Enviar esta cotización al cliente por correo?" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Enviar cotización
            </button>
        @endif
        @if ($cotizacion->estado === 'aceptada')
            <button wire:click="marcarEntregada" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Marcar entregada
            </button>
        @endif
        @if ($cotizacion->estado === 'entregada')
            <button wire:click="marcarFacturada" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Marcar facturada
            </button>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-4">
        <h2 class="font-bold text-sm">Hilo de comunicación</h2>
        <div class="flex flex-col gap-3 max-h-96 overflow-y-auto">
            @forelse ($cotizacion->mensajes as $m)
                <div class="border border-slate-100 dark:border-slate-800 rounded-lg p-3">
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">
                        {{ $m->autor_tipo === 'administrador' ? ($m->autor?->name ?? 'Administrador') : ucfirst($m->autor_tipo) }}
                        · {{ $m->created_at->diffForHumans() }}
                    </p>
                    <p class="text-sm whitespace-pre-line">{{ $m->contenido }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-400">Sin mensajes en el hilo.</p>
            @endforelse
        </div>
        <div class="flex items-end gap-3">
            <textarea wire:model="comentario" rows="2" placeholder="Comentario interno..." class="flex-1 border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2 text-sm outline-none focus:border-brand-blue"></textarea>
            <button wire:click="agregarComentario" class="text-[13.5px] font-semibold px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Comentar</button>
        </div>
        @error('comentario') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
    </div>
</div>
