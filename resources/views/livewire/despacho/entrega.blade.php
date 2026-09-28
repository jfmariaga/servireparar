<?php

use App\Livewire\Concerns\Notifies;
use App\Models\SolicitudDespacho;
use App\Services\Inventario\DespachoService;
use App\Support\Moneda;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layout', ['title' => 'Solicitud de despacho'])] class extends Component
{
    use Notifies, WithFileUploads;

    public SolicitudDespacho $solicitud;

    public bool $modoMensajero = false;

    public string $entregadoPor = '';
    public string $recibidoPorNombre = '';
    public string $recibidoPorDocumento = '';
    public string $notaEntrega = '';
    public string $firmaEntrega = '';
    public string $firma = '';
    public string $mensajeroNombre = '';
    public string $motivoAnulacion = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $fotoFirmaFisica = null;

    public function mount(SolicitudDespacho $solicitud): void
    {
        Gate::authorize('view', $solicitud);
        $this->solicitud = $solicitud;
        $this->entregadoPor = auth()->user()->name;
    }

    public function with(): array
    {
        $this->solicitud->load(['cliente', 'vendedor', 'detalles.inventario', 'remision']);

        return [
            'esAlmacen' => Gate::allows('gestionarAlmacen', $this->solicitud),
            'puedeAnular' => Gate::allows('anular', $this->solicitud),
            'lineasInventario' => $this->solicitud->detalles->where('origen', 'inventario'),
            'lineasExternas' => $this->solicitud->detalles->where('origen', 'compra_externa'),
        ];
    }

    public function recibir(DespachoService $despachos): void
    {
        Gate::authorize('gestionarAlmacen', $this->solicitud);
        $this->conManejoDeEstado(fn () => $despachos->recibir($this->solicitud, auth()->user()), 'Solicitud marcada como recibida.');
    }

    public function generarRemision(DespachoService $despachos): void
    {
        Gate::authorize('gestionarAlmacen', $this->solicitud);
        $this->conManejoDeEstado(fn () => $despachos->generarRemision($this->solicitud, auth()->user()), 'Remisión generada.');
    }

    public function confirmarEntrega(DespachoService $despachos): void
    {
        Gate::authorize('gestionarAlmacen', $this->solicitud);

        $this->validate([
            'entregadoPor' => 'required|string|max:150',
            'recibidoPorNombre' => 'required|string|max:150',
            'recibidoPorDocumento' => 'required|string|max:40',
            'notaEntrega' => 'nullable|string|max:1000',
            'firmaEntrega' => 'required|string',
            'firma' => 'nullable|string',
        ], [], [
            'entregadoPor' => 'nombre de quien entrega',
            'recibidoPorNombre' => 'nombre de quien recibe',
            'recibidoPorDocumento' => 'documento de quien recibe',
            'notaEntrega' => 'nota de entrega',
            'firmaEntrega' => 'firma de quien entrega',
            'firma' => 'firma de quien recibe',
        ]);

        try {
            $correoEnviado = $despachos->confirmarEntrega(
                $this->solicitud,
                auth()->user(),
                $this->recibidoPorNombre,
                $this->recibidoPorDocumento,
                $this->firma,
                $this->entregadoPor,
                $this->notaEntrega !== '' ? $this->notaEntrega : null,
                $this->firmaEntrega,
            );
        } catch (ValidationException $e) {
            $this->notifyError(collect($e->errors())->flatten()->first() ?? 'No se pudo confirmar la entrega.');

            return;
        }

        $this->solicitud->refresh();
        $this->reset(['firma', 'firmaEntrega']);

        $this->notifySuccess($correoEnviado
            ? 'Entrega confirmada. Copia de la remisión enviada a '.$this->solicitud->cliente->correo.'.'
            : 'Entrega confirmada. El cliente no tiene correo registrado: no se envió copia.');
    }

    public function confirmarSalidaMensajero(DespachoService $despachos): void
    {
        Gate::authorize('gestionarAlmacen', $this->solicitud);

        $this->validate([
            'entregadoPor' => 'required|string|max:150',
            'mensajeroNombre' => 'nullable|string|max:150',
            'notaEntrega' => 'nullable|string|max:1000',
            'firmaEntrega' => 'required|string',
        ], [], [
            'entregadoPor' => 'nombre de quien entrega',
            'mensajeroNombre' => 'nombre del mensajero',
            'notaEntrega' => 'nota de entrega',
            'firmaEntrega' => 'firma de quien entrega',
        ]);

        try {
            $despachos->confirmarSalidaMensajero(
                $this->solicitud,
                auth()->user(),
                $this->firmaEntrega,
                $this->entregadoPor,
                $this->mensajeroNombre !== '' ? $this->mensajeroNombre : null,
                $this->notaEntrega !== '' ? $this->notaEntrega : null,
            );
        } catch (ValidationException $e) {
            $this->notifyError(collect($e->errors())->flatten()->first() ?? 'No se pudo despachar con mensajero.');

            return;
        }

        $this->solicitud->refresh();
        $this->reset(['firmaEntrega', 'modoMensajero']);
        $this->notifySuccess('Salida con mensajero confirmada. Imprime la remisión para que la lleve — el stock ya se descontó.');
    }

    public function confirmarFirmaFisica(DespachoService $despachos): void
    {
        Gate::authorize('gestionarAlmacen', $this->solicitud);

        $this->validate([
            'recibidoPorNombre' => 'required|string|max:150',
            'recibidoPorDocumento' => 'required|string|max:40',
            'fotoFirmaFisica' => 'required|image|max:8192',
        ], [], [
            'recibidoPorNombre' => 'nombre de quien recibió',
            'recibidoPorDocumento' => 'documento de quien recibió',
            'fotoFirmaFisica' => 'foto del papel firmado',
        ]);

        $ruta = $this->fotoFirmaFisica->store('remisiones', 'public');

        try {
            $correoEnviado = $despachos->confirmarFirmaFisica(
                $this->solicitud,
                auth()->user(),
                $this->recibidoPorNombre,
                $this->recibidoPorDocumento,
                $ruta,
            );
        } catch (ValidationException $e) {
            $this->notifyError(collect($e->errors())->flatten()->first() ?? 'No se pudo registrar la firma física.');

            return;
        }

        $this->solicitud->refresh();
        $this->reset(['recibidoPorNombre', 'recibidoPorDocumento', 'fotoFirmaFisica']);

        $this->notifySuccess($correoEnviado
            ? 'Firma física registrada. Copia de la remisión enviada a '.$this->solicitud->cliente->correo.'.'
            : 'Firma física registrada. El cliente no tiene correo registrado: no se envió copia.');
    }

    public function anular(DespachoService $despachos): void
    {
        Gate::authorize('anular', $this->solicitud);
        $this->conManejoDeEstado(
            fn () => $despachos->anular($this->solicitud, auth()->user(), $this->motivoAnulacion !== '' ? $this->motivoAnulacion : null),
            'Solicitud anulada.'
        );
    }

    private function conManejoDeEstado(callable $accion, string $ok): void
    {
        try {
            $accion();
        } catch (ValidationException $e) {
            $this->notifyError(collect($e->errors())->flatten()->first() ?? 'No se pudo completar la acción.');

            return;
        }

        $this->solicitud->refresh();
        $this->reset(['firma', 'firmaEntrega']);
        $this->notifySuccess($ok);
    }
}; ?>

<div class="w-full flex flex-col gap-6">
    <x-breadcrumbs :items="[['label' => 'Despachos', 'route' => 'despachos.index'], ['label' => $solicitud->numero]]" />

    @if (session('ok'))
        <div class="bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 text-sm rounded-xl px-4 py-3">{{ session('ok') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_460px] gap-6 items-start">
    <div class="flex flex-col gap-6 min-w-0 lg:order-2">

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="font-mono text-sm text-slate-500 dark:text-slate-400">{{ $solicitud->numero }} · {{ $solicitud->sede }}{{ config('despachos.sedes.'.$solicitud->sede) ? ' — '.config('despachos.sedes.'.$solicitud->sede) : '' }}</div>
                <h2 class="font-bold text-lg text-slate-800 dark:text-slate-100">{{ $solicitud->cliente->nombre }}</h2>
                <div class="text-sm text-slate-500 dark:text-slate-400">Vendedor: {{ $solicitud->vendedor->name }} · {{ $solicitud->fecha_solicitud->format('d/m/Y') }}</div>
            </div>
            <span @class([
                'px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wide',
                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => in_array($solicitud->estado, ['solicitada', 'recibida']),
                'bg-brand-blue-tint text-brand-blue' => $solicitud->estado === 'remisionada',
                'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $solicitud->estado === 'despachada',
                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $solicitud->estado === 'entregada',
                'bg-brand-red-tint text-brand-red' => $solicitud->estado === 'anulada',
            ])>{{ $solicitud->estado === 'despachada' ? 'con mensajero' : $solicitud->estado }}</span>
        </div>

        @if ($solicitud->observaciones)
            <p class="text-sm text-slate-600 dark:text-slate-300">{{ $solicitud->observaciones }}</p>
        @endif

        {{-- Trazabilidad de un vistazo para quien despacha --}}
        <div class="flex flex-wrap gap-2 text-xs font-semibold">
            <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                {{ $lineasInventario->count() }} {{ $lineasInventario->count() === 1 ? 'línea' : 'líneas' }} para despachar de bodega
            </span>
            @if ($lineasExternas->isNotEmpty())
                <span class="px-2.5 py-1 rounded-lg bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">
                    {{ $lineasExternas->count() }} {{ $lineasExternas->count() === 1 ? 'línea' : 'líneas' }} de compra externa (no está en almacén)
                </span>
            @endif
        </div>

        @if ($solicitud->remision)
            <div class="text-sm text-slate-600 dark:text-slate-300">
                Remisión <span class="font-mono">{{ $solicitud->remision->numero }}</span>
                @if (in_array($solicitud->estado, ['despachada', 'entregada']))
                    · <a href="{{ route('despachos.remision', $solicitud) }}" target="_blank" class="text-brand-blue font-semibold hover:underline">Ver PDF</a>
                @endif
                @if ($solicitud->estado === 'despachada')
                    <span class="block text-xs text-amber-600 dark:text-amber-400 mt-0.5">
                        Salió con {{ $solicitud->mensajero_nombre ?: 'mensajero' }} el {{ $solicitud->despachada_en->format('d/m/Y H:i') }} —
                        pendiente de que vuelva con la firma física del cliente.
                    </span>
                @endif
            </div>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 font-bold text-sm text-slate-700 dark:text-slate-200">Despachar de bodega</div>
        <table class="w-full text-sm">
            <tbody>
                @forelse ($lineasInventario as $d)
                    @php $falta = $d->movimiento_id === null && (float) $d->cantidad > (float) $d->inventario->stock_actual; @endphp
                    <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                        <td class="px-5 py-3 text-slate-700 dark:text-slate-200">
                            {{ $d->inventario->nombre }} <span class="font-mono text-xs text-slate-400">{{ $d->inventario->codigo }}</span>
                            @if ($falta)
                                <span class="ml-2 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-brand-red-tint text-brand-red">stock insuficiente</span>
                            @endif
                            <div class="text-xs text-slate-400">Stock actual: {{ rtrim(rtrim(number_format((float) $d->inventario->stock_actual, 2), '0'), '.') }}</div>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap text-slate-700 dark:text-slate-200">{{ rtrim(rtrim(number_format((float) $d->cantidad, 2), '0'), '.') }}</td>
                        <td class="px-5 py-3 text-right whitespace-nowrap text-slate-500 dark:text-slate-400">
                            {{ Moneda::cop($d->costo_unitario) }}
                            <div class="text-[10px] text-slate-400">{{ $d->movimiento_id ? 'costo FIFO real' : 'ref.' }}</div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-5 py-4 text-slate-400">Sin líneas de bodega.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-5 py-3.5 border-t border-slate-100 dark:border-slate-800 font-bold text-sm text-slate-700 dark:text-slate-200">
            Compra externa <span class="font-normal text-slate-400">— no disponible en almacén, no afecta el inventario</span>
        </div>
        <table class="w-full text-sm">
            <tbody>
                @forelse ($lineasExternas as $d)
                    <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                        <td class="px-5 py-3 text-slate-700 dark:text-slate-200">
                            {{ $d->descripcion }}
                            <div class="text-xs text-slate-400">{{ $d->proveedor_externo }} · {{ $d->motivo }}</div>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap text-slate-700 dark:text-slate-200">{{ rtrim(rtrim(number_format((float) $d->cantidad, 2), '0'), '.') }}</td>
                        <td class="px-5 py-3 text-right whitespace-nowrap text-slate-500 dark:text-slate-400">{{ Moneda::cop($d->costo_compra_externa) }}</td>
                    </tr>
                @empty
                    <tr><td class="px-5 py-4 text-slate-400">Sin compras externas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    </div>

    <div class="flex flex-col gap-6 lg:sticky lg:top-6 lg:order-1">
    @if ($esAlmacen && ! in_array($solicitud->estado, ['entregada', 'anulada']))
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-4">
            @if ($solicitud->estado === 'solicitada')
                <button wire:click="recibir" class="self-start bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg">Marcar como recibida</button>
            @elseif ($solicitud->estado === 'recibida')
                <button wire:click="generarRemision" class="self-start bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg">Generar remisión</button>
            @elseif ($solicitud->estado === 'remisionada')
                <div class="flex gap-2 text-[13px] font-semibold">
                    <button type="button" wire:click="$set('modoMensajero', false)" @class([
                        'px-3 py-1.5 rounded-lg border',
                        'bg-brand-blue text-white border-brand-blue' => ! $modoMensajero,
                        'border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400' => $modoMensajero,
                    ])>Entrega en mostrador</button>
                    <button type="button" wire:click="$set('modoMensajero', true)" @class([
                        'px-3 py-1.5 rounded-lg border',
                        'bg-brand-blue text-white border-brand-blue' => $modoMensajero,
                        'border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400' => ! $modoMensajero,
                    ])>Envío con mensajero</button>
                </div>

                @if (! $modoMensajero)
                <div wire:key="panel-mostrador" class="contents">
                    <h3 class="font-bold text-sm text-slate-700 dark:text-slate-200">Confirmar entrega (firma del receptor)</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Entrega (nombre) *</label>
                            <input type="text" wire:model="entregadoPor" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                            @error('entregadoPor') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div></div>
                        <div>
                            <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Recibe (nombre) *</label>
                            <input type="text" wire:model="recibidoPorNombre" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                            @error('recibidoPorNombre') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Documento (C.C.) *</label>
                            <input type="text" wire:model="recibidoPorDocumento" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                            @error('recibidoPorDocumento') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Descripción de la entrega</label>
                            <textarea wire:model="notaEntrega" rows="3" placeholder="Ej.: Se realiza la entrega de 2 ventiladores (extractores) en buen estado al señor…" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue"></textarea>
                            @error('notaEntrega') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <p class="text-xs text-slate-400">Al confirmar, se envía una copia del PDF al correo del cliente
                        @if ($solicitud->cliente->correo) ({{ $solicitud->cliente->correo }}) @else (el cliente no tiene correo registrado) @endif.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div wire:key="firma-entrega-mostrador" x-data="firmaPad($wire, 'firmaEntrega')" class="flex flex-col gap-2">
                            <label class="block font-semibold text-slate-600 dark:text-slate-300">Firma de quien entrega *</label>
                            <canvas x-ref="canvas" width="600" height="180"
                                    class="border border-slate-300 dark:border-slate-600 rounded-lg bg-white touch-none w-full max-w-full"></canvas>
                            <button type="button" @click="limpiar" class="self-start text-xs font-semibold text-slate-500 dark:text-slate-400 hover:underline">Borrar y volver a firmar</button>
                            @error('firmaEntrega') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div wire:key="firma-recibe-mostrador" x-data="firmaPad($wire, 'firma')" class="flex flex-col gap-2">
                            <label class="block font-semibold text-slate-600 dark:text-slate-300">Firma de quien recibe <span class="font-normal text-slate-400">(opcional — puede firmar el papel físico)</span></label>
                            <canvas x-ref="canvas" width="600" height="180"
                                    class="border border-slate-300 dark:border-slate-600 rounded-lg bg-white touch-none w-full max-w-full"></canvas>
                            <button type="button" @click="limpiar" class="self-start text-xs font-semibold text-slate-500 dark:text-slate-400 hover:underline">Borrar y volver a firmar</button>
                            @error('firma') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <button wire:click="confirmarEntrega" class="self-start bg-emerald-600 hover:bg-emerald-700 text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg">Confirmar entrega</button>
                </div>
                @else
                <div wire:key="panel-mensajero" class="contents">
                    <h3 class="font-bold text-sm text-slate-700 dark:text-slate-200">Despachar con mensajero (firma de quien entrega)</h3>
                    <p class="text-xs text-slate-400 max-w-lg">El mensajero se lleva la remisión impresa con esta firma para que el cliente firme físicamente. El stock se descuenta ahora; la solicitud queda pendiente hasta que registres la firma de vuelta.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Entrega (nombre) *</label>
                            <input type="text" wire:model="entregadoPor" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                            @error('entregadoPor') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Mensajero (nombre)</label>
                            <input type="text" wire:model="mensajeroNombre" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                            @error('mensajeroNombre') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Descripción de la entrega</label>
                            <textarea wire:model="notaEntrega" rows="3" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue"></textarea>
                            @error('notaEntrega') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div wire:key="firma-entrega-mensajero" x-data="firmaPad($wire, 'firmaEntrega')" class="flex flex-col gap-2 max-w-md">
                        <label class="block font-semibold text-slate-600 dark:text-slate-300">Firma de quien entrega *</label>
                        <canvas x-ref="canvas" width="600" height="180"
                                class="border border-slate-300 dark:border-slate-600 rounded-lg bg-white touch-none w-full max-w-full"></canvas>
                        <button type="button" @click="limpiar" class="self-start text-xs font-semibold text-slate-500 dark:text-slate-400 hover:underline">Borrar y volver a firmar</button>
                        @error('firmaEntrega') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                    </div>

                    <button wire:click="confirmarSalidaMensajero" class="self-start bg-amber-600 hover:bg-amber-700 text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg">Confirmar salida con mensajero</button>
                </div>
                @endif
            @elseif ($solicitud->estado === 'despachada')
                <h3 class="font-bold text-sm text-slate-700 dark:text-slate-200">Registrar firma física de vuelta</h3>
                <p class="text-xs text-slate-400 max-w-lg">Cuando el mensajero regrese con el papel firmado por el cliente, adjunta una foto del documento para cerrar la entrega.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Recibe (nombre) *</label>
                        <input type="text" wire:model="recibidoPorNombre" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                        @error('recibidoPorNombre') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Documento (C.C.) *</label>
                        <input type="text" wire:model="recibidoPorDocumento" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                        @error('recibidoPorDocumento') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Foto del papel firmado *</label>
                        <x-file-input wire:model="fotoFirmaFisica" class="w-full" />
                        <div wire:loading wire:target="fotoFirmaFisica" class="text-xs text-slate-400 mt-1">Subiendo foto…</div>
                        @if ($fotoFirmaFisica)
                            <img src="{{ $fotoFirmaFisica->temporaryUrl() }}" class="mt-2 max-h-40 rounded-lg border border-slate-200 dark:border-slate-700">
                        @endif
                        @error('fotoFirmaFisica') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <button wire:click="confirmarFirmaFisica" class="self-start bg-emerald-600 hover:bg-emerald-700 text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg">Registrar firma física recibida</button>
            @endif

            @if ($puedeAnular)
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-end gap-2">
                    <div class="flex-1 min-w-[12rem]">
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Motivo de anulación (opcional)</label>
                        <input type="text" wire:model="motivoAnulacion" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 text-sm outline-none focus:border-brand-blue">
                    </div>
                    <button wire:click="anular" class="text-[13px] font-semibold px-4 py-2 rounded-lg border border-brand-red/40 text-brand-red hover:bg-brand-red-tint">Anular solicitud</button>
                </div>
            @endif
        </div>
    @elseif ($puedeAnular)
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[12rem]">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Motivo de anulación (opcional)</label>
                <input type="text" wire:model="motivoAnulacion" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 text-sm outline-none focus:border-brand-blue">
            </div>
            <button wire:click="anular" class="text-[13px] font-semibold px-4 py-2 rounded-lg border border-brand-red/40 text-brand-red hover:bg-brand-red-tint">Anular solicitud</button>
        </div>
    @endif
    </div>
    </div>
</div>

@script
<script>
    Alpine.data('firmaPad', ($wire, campo, onChange = null) => ({
        drawing: false,
        ctx: null,
        init() {
            this.ctx = this.$refs.canvas.getContext('2d');
            this.ctx.lineWidth = 2;
            this.ctx.lineCap = 'round';
            this.ctx.strokeStyle = '#0f172a';
            const pos = (e) => {
                const r = this.$refs.canvas.getBoundingClientRect();
                const t = e.touches ? e.touches[0] : e;
                return {
                    x: (t.clientX - r.left) * (this.$refs.canvas.width / r.width),
                    y: (t.clientY - r.top) * (this.$refs.canvas.height / r.height),
                };
            };
            const start = (e) => { this.drawing = true; const p = pos(e); this.ctx.beginPath(); this.ctx.moveTo(p.x, p.y); e.preventDefault(); };
            const move = (e) => { if (!this.drawing) return; const p = pos(e); this.ctx.lineTo(p.x, p.y); this.ctx.stroke(); e.preventDefault(); };
            const end = () => { if (!this.drawing) return; this.drawing = false; const data = this.$refs.canvas.toDataURL('image/png'); $wire.set(campo, data, false); onChange && onChange(data); };
            this.$refs.canvas.addEventListener('mousedown', start);
            this.$refs.canvas.addEventListener('mousemove', move);
            window.addEventListener('mouseup', end);
            this.$refs.canvas.addEventListener('touchstart', start);
            this.$refs.canvas.addEventListener('touchmove', move);
            this.$refs.canvas.addEventListener('touchend', end);
        },
        limpiar() {
            this.ctx.clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height);
            $wire.set(campo, '', false);
            onChange && onChange('');
        },
    }));
</script>
@endscript
