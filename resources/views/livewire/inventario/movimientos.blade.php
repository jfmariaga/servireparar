<?php

use App\Exceptions\StockInsuficienteException;
use App\Livewire\Concerns\Notifies;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\Inventario\MovimientoService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layout', ['title' => 'Solicitudes de inventario'])] class extends Component
{
    use Notifies, WithPagination;

    public bool $mostrarForm = false;
    public ?int $clienteId = null;
    public ?int $inventarioId = null;
    public string $cantidad = '';

    public bool $mostrarFormEntrada = false;
    public ?int $entradaInventarioId = null;
    public ?int $entradaProveedorId = null;
    public string $entradaCantidad = '';
    public string $entradaCosto = '';

    public ?int $devolviendoId = null;
    public string $nuevoEstadoHerramienta = 'disponible';

    public function mount(): void
    {
        Gate::authorize('viewAny', Inventario::class);
    }

    public function with(): array
    {
        return [
            'clientes' => Cliente::activos()->orderBy('nombre')->get(),
            'proveedores' => Proveedor::activos()->orderBy('nombre')->get(),
            'consumibles' => Inventario::activos()->where('tipo', 'consumible')->orderBy('nombre')->get(),
            'todosLosItems' => Inventario::activos()->orderBy('nombre')->get(),
            'solicitudesManuales' => MovimientoInventario::where('origen', 'manual')
                ->with(['inventario', 'cliente'])
                ->orderByDesc('fecha')
                ->paginate(8, pageName: 'manualesPage'),
            'herramientasEnUso' => Inventario::where('tipo', 'herramienta')
                ->where('estado_herramienta', 'en_uso')
                ->orderBy('nombre')
                ->paginate(8, pageName: 'herramientasPage'),
            'entradasRecientes' => MovimientoInventario::where('origen', 'entrada_proveedor')
                ->with(['inventario', 'proveedor'])
                ->orderByDesc('fecha')
                ->paginate(8, pageName: 'entradasPage'),
        ];
    }

    public function nuevaSolicitud(): void
    {
        Gate::authorize('create', Inventario::class);
        $this->reset(['clienteId', 'inventarioId', 'cantidad']);
        $this->mostrarForm = true;
    }

    public function registrarSolicitud(): void
    {
        Gate::authorize('create', Inventario::class);

        $datos = $this->validate([
            'clienteId' => 'required|exists:clientes,id',
            'inventarioId' => 'required|exists:inventario,id',
            'cantidad' => 'required|numeric|min:0.01',
        ]);

        $item = Inventario::findOrFail($datos['inventarioId']);
        $cliente = Cliente::findOrFail($datos['clienteId']);

        try {
            (new MovimientoService())->salida(
                $item,
                (float) $datos['cantidad'],
                auth()->user(),
                origen: 'manual',
                cliente: $cliente,
                motivo: 'Solicitud manual de insumo sin OT asociada.',
            );
        } catch (StockInsuficienteException $e) {
            $this->addError('cantidad', $e->getMessage());

            return;
        }

        $this->mostrarForm = false;
        $this->notifySuccess('Solicitud registrada y descontada del inventario.');
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
    }

    public function nuevaEntrada(): void
    {
        Gate::authorize('create', Inventario::class);
        $this->reset(['entradaInventarioId', 'entradaProveedorId', 'entradaCantidad', 'entradaCosto']);
        $this->mostrarFormEntrada = true;
    }

    /**
     * FR-014: toda entrada registra el proveedor. Cada entrada es su propio lote
     * (cantidad + costo); `salida()` los consume por FIFO (ver MovimientoService).
     */
    public function registrarEntrada(): void
    {
        Gate::authorize('create', Inventario::class);

        $datos = $this->validate([
            'entradaInventarioId' => 'required|exists:inventario,id',
            'entradaProveedorId' => 'required|exists:proveedores,id',
            'entradaCantidad' => 'required|numeric|min:0.01',
            'entradaCosto' => 'nullable|numeric|min:0',
        ]);

        $item = Inventario::findOrFail($datos['entradaInventarioId']);
        $proveedor = Proveedor::findOrFail($datos['entradaProveedorId']);

        (new MovimientoService())->entrada(
            $item,
            (float) $datos['entradaCantidad'],
            auth()->user(),
            proveedor: $proveedor,
            costoUnitario: $datos['entradaCosto'] !== '' ? (float) $datos['entradaCosto'] : null,
        );

        $this->mostrarFormEntrada = false;
        $this->notifySuccess('Entrada registrada: '.$item->nombre.' (+'.$datos['entradaCantidad'].').');
    }

    public function cancelarEntrada(): void
    {
        $this->mostrarFormEntrada = false;
    }

    public function iniciarDevolucion(int $inventarioId): void
    {
        $this->devolviendoId = $inventarioId;
        $this->nuevoEstadoHerramienta = 'disponible';
    }

    public function confirmarDevolucion(): void
    {
        Gate::authorize('update', Inventario::class);

        $item = Inventario::findOrFail($this->devolviendoId);

        (new MovimientoService())->devolucion(
            $item,
            auth()->user(),
            $this->nuevoEstadoHerramienta,
        );

        $this->devolviendoId = null;
        $this->notifySuccess('Devolución registrada: '.$item->nombre.' → '.str_replace('_', ' ', $this->nuevoEstadoHerramienta).'.');
    }

    public function cancelarDevolucion(): void
    {
        $this->devolviendoId = null;
    }
}; ?>

<div class="flex flex-col gap-6">
    @include('partials.inventario-tabs')

    <div class="flex items-center justify-end gap-2">
        <x-icon-button wire:click="nuevaEntrada" title="Registrar entrada">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
        </x-icon-button>
        <x-icon-button wire:click="nuevaSolicitud" title="Solicitud de insumo" variant="primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
        </x-icon-button>
    </div>

    @if ($mostrarFormEntrada)
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6">
            <h2 class="font-bold mb-4">Registrar entrada de inventario</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                Cada entrada queda registrada como su propio lote, con su cantidad y su costo. Al salir del inventario, se consume primero el lote más antiguo (FIFO), así el costo de cada salida es el costo real de lo que efectivamente salió.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-sm">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Ítem *</label>
                    <x-select wire:model="entradaInventarioId">
                        @foreach ($todosLosItems as $item)
                            <option value="{{ $item->id }}">{{ $item->nombre }} ({{ $item->codigo }})</option>
                        @endforeach
                    </x-select>
                    @error('entradaInventarioId') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Proveedor *</label>
                    <x-select wire:model="entradaProveedorId">
                        @foreach ($proveedores as $proveedor)
                            <option value="{{ $proveedor->id }}">{{ $proveedor->nombre }}</option>
                        @endforeach
                    </x-select>
                    @error('entradaProveedorId') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Cantidad *</label>
                    <input type="number" step="0.01" min="0.01" wire:model="entradaCantidad" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('entradaCantidad') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Costo unitario de esta entrada</label>
                    <input type="number" step="0.01" min="0" wire:model="entradaCosto" placeholder="Opcional" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('entradaCosto') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex gap-2 mt-6">
                <button wire:click="registrarEntrada" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">Registrar</button>
                <button wire:click="cancelarEntrada" class="text-[13.5px] font-semibold px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Cancelar</button>
            </div>
        </div>
    @endif

    @if ($mostrarForm)
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6">
            <h2 class="font-bold mb-4">Solicitud de insumo (sin orden de trabajo)</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Salida directa hacia un cliente externo, con su propia trazabilidad de costos.</p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Cliente *</label>
                    <x-select wire:model="clienteId">
                        @foreach ($clientes as $cliente)
                            <option value="{{ $cliente->id }}">{{ $cliente->nombre }}</option>
                        @endforeach
                    </x-select>
                    @error('clienteId') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Insumo *</label>
                    <x-select wire:model="inventarioId">
                        @foreach ($consumibles as $consumible)
                            <option value="{{ $consumible->id }}">{{ $consumible->nombre }} ({{ $consumible->codigo }}) — stock: {{ rtrim(rtrim(number_format((float) $consumible->stock_actual, 2), '0'), '.') }}</option>
                        @endforeach
                    </x-select>
                    @error('inventarioId') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Cantidad *</label>
                    <input type="number" step="0.01" min="0.01" wire:model="cantidad" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('cantidad') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex gap-2 mt-6">
                <button wire:click="registrarSolicitud" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">Registrar</button>
                <button wire:click="cancelar" class="text-[13.5px] font-semibold px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Cancelar</button>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 font-bold text-sm">Solicitudes manuales</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                            <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Fecha</th>
                            <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Cliente</th>
                            <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Insumo</th>
                            <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Cant.</th>
                            <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Costo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($solicitudesManuales as $mov)
                            <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                                <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $mov->fecha->format('d/m/y') }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">{{ $mov->cliente?->nombre ?? '—' }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">{{ $mov->inventario->nombre }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $mov->cantidad, 2), '0'), '.') }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">{{ \App\Support\Moneda::cop($mov->costo_unitario) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">Sin solicitudes manuales registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3.5">{{ $solicitudesManuales->links() }}</div>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 font-bold text-sm">Herramientas pendientes de devolución</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                            <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Herramienta</th>
                            <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Código</th>
                            <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($herramientasEnUso as $herramienta)
                            <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                                <td class="px-5 py-3 whitespace-nowrap">{{ $herramienta->nombre }}</td>
                                <td class="px-5 py-3 font-mono text-[12.5px] whitespace-nowrap">{{ $herramienta->codigo }}</td>
                                <td class="px-5 py-3">
                                    @if ($devolviendoId === $herramienta->id)
                                        <div class="flex items-center gap-2">
                                            <div class="w-44">
                                                <x-select wire:model="nuevoEstadoHerramienta" :placeholder="null">
                                                    <option value="disponible">Disponible</option>
                                                    <option value="dañada">Dañada</option>
                                                    <option value="en_mantenimiento">En mantenimiento</option>
                                                </x-select>
                                            </div>
                                            <button wire:click="confirmarDevolucion" class="text-xs font-semibold text-white bg-brand-blue hover:bg-brand-blue-dark rounded-lg px-2.5 py-1.5">Confirmar</button>
                                            <button wire:click="cancelarDevolucion" class="text-xs font-semibold text-slate-500 hover:underline">Cancelar</button>
                                        </div>
                                    @else
                                        <button wire:click="iniciarDevolucion({{ $herramienta->id }})" class="text-xs font-semibold text-brand-blue hover:underline">Registrar devolución</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-8 text-center text-slate-400">No hay herramientas pendientes de devolución.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3.5">{{ $herramientasEnUso->links() }}</div>
        </div>

    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 font-bold text-sm">Entradas recientes</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Fecha</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Ítem</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Proveedor</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Cant.</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Costo de entrada</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entradasRecientes as $mov)
                        <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $mov->fecha->format('d/m/y') }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">{{ $mov->inventario->nombre }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $mov->proveedor?->nombre ?? '—' }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $mov->cantidad, 2), '0'), '.') }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">{{ \App\Support\Moneda::cop($mov->costo_unitario) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">Sin entradas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3.5">{{ $entradasRecientes->links() }}</div>
    </div>
</div>
