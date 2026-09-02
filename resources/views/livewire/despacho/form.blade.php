<?php

use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\SolicitudDespacho;
use App\Services\Inventario\DespachoService;
use App\Support\Moneda;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Nueva solicitud de despacho'])] class extends Component
{
    public ?int $clienteId = null;
    public string $sede = '';
    public string $observaciones = '';

    /** @var array<int, array<string, mixed>> */
    public array $lineas = [];

    public function mount(): void
    {
        Gate::authorize('create', SolicitudDespacho::class);
        $this->sede = config('despachos.sede_por_defecto', 'BAQ');
        $this->agregarLinea();
    }

    public function with(): array
    {
        $items = Inventario::activos()->orderBy('nombre')->get(['id', 'nombre', 'codigo', 'stock_actual', 'tipo', 'costo_unitario']);

        $totalEstimado = collect($this->lineas)->sum(function (array $l) use ($items) {
            $cantidad = (float) ($l['cantidad'] ?: 0);

            if (($l['origen'] ?? 'inventario') === 'compra_externa') {
                return $cantidad * (float) ($l['costo_compra_externa'] ?: 0);
            }

            $item = $items->firstWhere('id', (int) ($l['inventario_id'] ?? 0));

            return $cantidad * (float) ($item->costo_unitario ?? 0);
        });

        return [
            'clientes' => Cliente::activos()->orderBy('nombre')->get(),
            'items' => $items,
            'totalEstimado' => Moneda::cop($totalEstimado),
            'sedes' => config('despachos.sedes', []),
        ];
    }

    public function agregarLinea(): void
    {
        $this->lineas[] = [
            'uid' => (string) Str::uuid(),
            'origen' => 'inventario',
            'inventario_id' => null,
            'descripcion' => '',
            'cantidad' => '',
            'proveedor_externo' => '',
            'costo_compra_externa' => '',
        ];
    }

    public function quitarLinea(int $i): void
    {
        unset($this->lineas[$i]);
        $this->lineas = array_values($this->lineas);
        if ($this->lineas === []) {
            $this->agregarLinea();
        }
    }

    public function enviar(DespachoService $despachos): void
    {
        Gate::authorize('create', SolicitudDespacho::class);

        $datos = $this->validate([
            'clienteId' => 'required|exists:clientes,id',
            'sede' => 'required|string|in:'.implode(',', array_keys(config('despachos.sedes', []))),
            'lineas' => 'required|array|min:1',
            'lineas.*.origen' => 'required|in:inventario,compra_externa',
            'lineas.*.cantidad' => 'required|numeric|min:0.01',
            'lineas.*.inventario_id' => 'nullable|required_if:lineas.*.origen,inventario|exists:inventario,id',
            'lineas.*.descripcion' => 'nullable|required_if:lineas.*.origen,compra_externa|string|max:200',
            'lineas.*.proveedor_externo' => 'nullable|required_if:lineas.*.origen,compra_externa|string|max:150',
            'lineas.*.costo_compra_externa' => 'nullable|required_if:lineas.*.origen,compra_externa|numeric|min:0',
        ], [], [
            'clienteId' => 'cliente',
            'lineas.*.cantidad' => 'cantidad',
            'lineas.*.inventario_id' => 'ítem',
            'lineas.*.descripcion' => 'descripción',
            'lineas.*.proveedor_externo' => 'proveedor externo',
            'lineas.*.costo_compra_externa' => 'costo de compra externa',
        ]);

        try {
            $solicitud = $despachos->crear(
                auth()->user(),
                (int) $datos['clienteId'],
                $this->observaciones !== '' ? $this->observaciones : null,
                $this->lineas,
                $this->sede,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }

            return;
        }

        session()->flash('ok', 'Solicitud '.$solicitud->numero.' enviada al almacén.');
        $this->redirectRoute('despachos.detalle', $solicitud, navigate: false);
    }
}; ?>

<div class="max-w-3xl flex flex-col gap-6">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-5">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Ciudad (remisión) *</label>
                <x-select wire:model="sede" :placeholder="null">
                    @foreach ($sedes as $codigo => $ciudad)
                        <option value="{{ $codigo }}">{{ $codigo }} — {{ $ciudad }}</option>
                    @endforeach
                </x-select>
                @error('sede') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Cliente *</label>
                <x-select wire:model="clienteId">
                    @foreach ($clientes as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                    @endforeach
                </x-select>
                @error('clienteId') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Observaciones</label>
                <input type="text" wire:model="observaciones" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-sm">Líneas solicitadas</h2>
                <button wire:click="agregarLinea" type="button" class="text-xs font-semibold text-brand-blue hover:underline">+ Agregar línea</button>
            </div>
            @error('lineas') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror

            @foreach ($lineas as $i => $linea)
                @php
                    $itemSel = ($linea['origen'] ?? 'inventario') === 'inventario'
                        ? $items->firstWhere('id', (int) ($linea['inventario_id'] ?? 0))
                        : null;
                    $faltaStock = $itemSel && (float) ($linea['cantidad'] ?: 0) > (float) $itemSel->stock_actual;
                @endphp
                <div wire:key="linea-{{ $linea['uid'] ?? $i }}" class="border border-slate-200 dark:border-slate-800 rounded-xl p-4 flex flex-col gap-3">
                    <div class="flex items-center gap-4 text-sm">
                        <label class="flex items-center gap-1.5">
                            <input type="radio" value="inventario" wire:model.live="lineas.{{ $i }}.origen"> De inventario
                        </label>
                        <label class="flex items-center gap-1.5">
                            <input type="radio" value="compra_externa" wire:model.live="lineas.{{ $i }}.origen"> No está en almacén (compra externa)
                        </label>
                        <button wire:click="quitarLinea({{ $i }})" type="button" class="ml-auto text-xs text-brand-red hover:underline">Quitar</button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                        @if (($linea['origen'] ?? 'inventario') === 'inventario')
                            <div class="sm:col-span-2">
                                <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Ítem *</label>
                                <x-select wire:model.live="lineas.{{ $i }}.inventario_id" :reset-key="'item-'.($linea['uid'] ?? $i)">
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}">{{ $item->nombre }} ({{ $item->codigo }}) — stock: {{ rtrim(rtrim(number_format((float) $item->stock_actual, 2), '0'), '.') }}</option>
                                    @endforeach
                                </x-select>
                                @error('lineas.'.$i.'.inventario_id') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                            </div>
                        @else
                            <div class="sm:col-span-2">
                                <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Descripción *</label>
                                <input type="text" wire:model="lineas.{{ $i }}.descripcion" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                                @error('lineas.'.$i.'.descripcion') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                            </div>
                        @endif
                        <div>
                            <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Cantidad *</label>
                            <input type="number" step="0.01" min="0.01" wire:model.live="lineas.{{ $i }}.cantidad" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                            @error('lineas.'.$i.'.cantidad') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    @if ($faltaStock)
                        <p class="text-xs font-semibold text-brand-red">Stock disponible ({{ rtrim(rtrim(number_format((float) $itemSel->stock_actual, 2), '0'), '.') }}) menor que la cantidad pedida. Puedes reducir la cantidad o marcar la línea como compra externa.</p>
                    @endif

                    @if (($linea['origen'] ?? 'inventario') === 'compra_externa')
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <div>
                                <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Proveedor externo *</label>
                                <input type="text" wire:model="lineas.{{ $i }}.proveedor_externo" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                                @error('lineas.'.$i.'.proveedor_externo') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Costo compra externa *</label>
                                <input type="number" step="0.01" min="0" wire:model.live="lineas.{{ $i }}.costo_compra_externa" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                                @error('lineas.'.$i.'.costo_compra_externa') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <p class="text-xs text-slate-400">Se registrará con el motivo fijo «No disponible en almacén». No afecta el inventario.</p>
                    @endif
                </div>
            @endforeach

            <div class="flex justify-end text-sm text-slate-500 dark:text-slate-400">
                Total estimado: <span class="font-bold text-slate-700 dark:text-slate-200 ml-2">{{ $totalEstimado }}</span>
            </div>
        </div>

        <div class="flex gap-2">
            <button wire:click="enviar" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">Enviar al almacén</button>
            <a href="{{ route('despachos.index') }}" wire:navigate class="text-[13.5px] font-semibold px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Cancelar</a>
        </div>
    </div>
</div>
