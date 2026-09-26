<?php

use App\Livewire\Concerns\Notifies;
use App\Models\Compra;
use App\Models\Inventario;
use App\Models\Proveedor;
use App\Services\Compras\CompraService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Nueva compra'])] class extends Component
{
    use Notifies;

    public ?int $proveedorId = null;

    public string $observaciones = '';

    /** @var array<int, array{uid: string, inventario_id: ?int, cantidad: float, costo_unitario: float}> */
    public array $items = [];

    public function mount(): void
    {
        Gate::authorize('manage', Compra::class);
        $this->agregarItem();
    }

    public function with(): array
    {
        return [
            'proveedores' => Proveedor::activos()->orderBy('nombre')->get(),
            'insumos' => Inventario::where('activo', true)->orderBy('nombre')->get(),
        ];
    }

    public function agregarItem(): void
    {
        $this->items[] = [
            'uid' => (string) Str::uuid(),
            'inventario_id' => null,
            'cantidad' => 1,
            'costo_unitario' => 0,
        ];
    }

    public function quitarItem(int $i): void
    {
        unset($this->items[$i]);
        $this->items = array_values($this->items);
    }

    public function guardar(): void
    {
        $datos = $this->validate([
            'proveedorId' => 'required|exists:proveedores,id',
            'observaciones' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.inventario_id' => 'required|exists:inventario,id',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.costo_unitario' => 'required|numeric|min:0',
        ], [], ['items' => 'ítems']);

        $compra = app(CompraService::class)->crear(
            Proveedor::findOrFail($datos['proveedorId']),
            auth()->user(),
            $datos['items'],
            $datos['observaciones'] ?: null,
        );

        $this->notifySuccess('Solicitud de compra '.$compra->numero.' creada.');
        $this->redirectRoute('compras.gestionar', $compra, navigate: false);
    }
}; ?>

<div class="max-w-3xl flex flex-col gap-6">
    <x-breadcrumbs :items="[['label' => 'Compras', 'route' => 'compras.tablero'], ['label' => 'Nueva']]" />

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Proveedor *</label>
                <x-select wire:model="proveedorId">
                    @foreach ($proveedores as $p)
                        <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                    @endforeach
                </x-select>
                @error('proveedorId') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Observaciones</label>
                <input type="text" wire:model="observaciones" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-sm">Ítems a comprar</h2>
                <button wire:click="agregarItem" type="button" class="text-xs font-semibold text-brand-blue hover:underline">+ Agregar ítem</button>
            </div>
            @error('items') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror

            @foreach ($items as $i => $item)
                <div wire:key="item-{{ $item['uid'] }}" class="border border-slate-200 dark:border-slate-800 rounded-xl p-4 grid grid-cols-1 sm:grid-cols-4 gap-3 text-sm">
                    <div class="sm:col-span-2">
                        <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Ítem *</label>
                        <x-select wire:model="items.{{ $i }}.inventario_id" :reset-key="'inv-'.$item['uid']">
                            @foreach ($insumos as $ins)
                                <option value="{{ $ins->id }}">{{ $ins->nombre }} ({{ $ins->codigo }})</option>
                            @endforeach
                        </x-select>
                        @error('items.'.$i.'.inventario_id') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Cantidad *</label>
                        <input type="number" step="0.01" min="0.01" wire:model="items.{{ $i }}.cantidad" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                    </div>
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Costo unit. *</label>
                            <input type="number" step="0.01" min="0" wire:model="items.{{ $i }}.costo_unitario" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                        </div>
                        <button wire:click="quitarItem({{ $i }})" type="button" class="text-xs text-brand-red hover:underline mb-2.5">Quitar</button>
                    </div>
                </div>
            @endforeach
        </div>

        <button wire:click="guardar" class="self-start bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
            Crear solicitud de compra
        </button>
    </div>
</div>
