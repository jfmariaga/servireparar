<?php

use App\Livewire\Concerns\Notifies;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Inventario;
use App\Models\Servicio;
use App\Models\SolicitudDespacho;
use App\Services\Cotizaciones\CotizacionService;
use App\Services\Inventario\DespachoService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Cotización'])] class extends Component
{
    use Notifies;

    public Cotizacion $cotizacion;

    public ?int $clienteIdAsignar = null;

    /** @var array<int, array{uid: string, tipo_item: string, servicio_id: ?int, inventario_id: ?int, origen: ?string, costo_unitario: float|string|null, cantidad: float}> */
    public array $items = [];

    public string $comentario = '';

    public string $sede = '';

    public string $descuentoPorcentaje = '0';

    public string $fechaPromesaEntrega = '';

    public function mount(Cotizacion $cotizacion): void
    {
        Gate::authorize('view', $cotizacion);
        $this->cotizacion = $cotizacion;
        $this->sede = (string) $cotizacion->sede;
        $this->descuentoPorcentaje = (string) (float) $cotizacion->descuento_porcentaje;
        $this->fechaPromesaEntrega = $cotizacion->fecha_promesa_entrega?->format('Y-m-d') ?? '';
        $this->cargarItemsDesdeDetalle();
    }

    public function with(): array
    {
        $this->cotizacion->load(['cliente', 'detalles.servicio', 'detalles.inventario', 'mensajes.autor', 'solicitudDespacho']);

        return [
            'clientes' => Cliente::activos()->orderBy('nombre')->get(),
            'servicios' => Servicio::activos()->orderBy('nombre')->get(),
            'insumos' => Inventario::where('activo', true)->orderBy('nombre')->get(),
            'sedes' => config('cotizaciones.sedes'),
            'puedeEditar' => $this->cotizacion->estado === 'en_revision',
            'puedeReabrir' => in_array($this->cotizacion->estado, ['cotizada', 'rechazada'], true),
            'puedeSolicitarDespacho' => $this->cotizacion->estado === 'aceptada'
                && ! $this->cotizacion->solicitudDespacho
                && $this->cotizacion->detalles->contains('tipo_item', 'insumo'),
        ];
    }

    public function guardarCabecera(): void
    {
        $datos = $this->validate([
            'sede' => 'required|in:'.implode(',', array_keys(config('cotizaciones.sedes'))),
            'descuentoPorcentaje' => 'required|numeric|min:0|max:100',
            'fechaPromesaEntrega' => 'nullable|date',
        ], [], ['sede' => 'sede', 'descuentoPorcentaje' => 'descuento', 'fechaPromesaEntrega' => 'fecha prometida de entrega']);

        $this->cotizacion = app(CotizacionService::class)->guardarCabecera($this->cotizacion, [
            'sede' => $datos['sede'],
            'descuento_porcentaje' => $datos['descuentoPorcentaje'],
            'fecha_promesa_entrega' => $datos['fechaPromesaEntrega'] !== '' ? $datos['fechaPromesaEntrega'] : null,
        ]);
        $this->notifySuccess('Datos de la cotización actualizados. Número: '.$this->cotizacion->numero);
    }

    public function aceptar(): void
    {
        $this->cotizacion = app(CotizacionService::class)->aceptar($this->cotizacion, auth()->user());
        $this->notifySuccess('Cotización marcada como aceptada.');
    }

    public function rechazar(): void
    {
        $this->cotizacion = app(CotizacionService::class)->rechazar($this->cotizacion, auth()->user());
        $this->notifySuccess('Cotización marcada como rechazada.');
    }

    public function reabrir(): void
    {
        $this->cotizacion = app(CotizacionService::class)->reabrir($this->cotizacion, auth()->user());
        $this->cargarItemsDesdeDetalle();
        $this->notifySuccess('Cotización reabierta. Edita los ítems y vuelve a enviarla.');
    }

    private function cargarItemsDesdeDetalle(): void
    {
        $this->items = $this->cotizacion->detalles->map(fn ($d) => [
            'uid' => (string) Str::uuid(),
            'tipo_item' => $d->tipo_item,
            'servicio_id' => $d->servicio_id,
            'inventario_id' => $d->inventario_id,
            'origen' => $d->origen ?? 'inventario',
            'costo_unitario' => (float) $d->costo_unitario,
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
            'origen' => 'inventario',
            'costo_unitario' => null,
            'cantidad' => 1,
        ];
    }

    public function quitarItem(int $i): void
    {
        unset($this->items[$i]);
        $this->items = array_values($this->items);
    }

    /**
     * Al elegir un insumo, prellena su precio de catálogo en la línea —
     * sigue siendo editable después, solo afecta esta cotización.
     */
    public function updated(string $name, mixed $value): void
    {
        if (preg_match('/^items\.(\d+)\.inventario_id$/', $name, $m) && $value !== '' && $value !== null) {
            $this->items[(int) $m[1]]['costo_unitario'] = (float) Inventario::find($value)?->costo_unitario;
        }
    }

    /**
     * Sugiere, en el hilo de comunicación, un comentario de control interno
     * para una línea de compra externa. El usuario lo completa y lo envía
     * desde el panel del hilo — nunca se refleja en la cotización.
     */
    public function sugerirComentarioControl(int $i): void
    {
        $item = $this->items[$i] ?? null;

        if (! $item) {
            return;
        }

        $nombre = $item['inventario_id'] ? Inventario::find($item['inventario_id'])?->nombre : null;

        $this->comentario = 'Compra externa — '.($nombre ?? 'insumo').' (cant. '.($item['cantidad'] ?: 0).'): ';
    }

    public function guardarItems(): void
    {
        $datos = $this->validate([
            'items' => 'required|array|min:1',
            'items.*.tipo_item' => 'required|in:servicio,insumo',
            'items.*.servicio_id' => 'nullable|exists:servicios,id',
            'items.*.inventario_id' => 'nullable|exists:inventario,id',
            'items.*.origen' => 'nullable|in:inventario,compra_externa',
            'items.*.costo_unitario' => 'nullable|numeric|min:0',
            'items.*.cantidad' => 'required|numeric|min:0.01',
        ], [], ['items' => 'ítems']);

        app(CotizacionService::class)->guardarItems($this->cotizacion, $datos['items']);
        $this->cotizacion->refresh();
        $this->cargarItemsDesdeDetalle();
        $this->notifySuccess('Ítems guardados. Total: '.\App\Support\Moneda::cop($this->cotizacion->total));
    }

    public function solicitarDespacho(): void
    {
        Gate::authorize('create', SolicitudDespacho::class);

        $solicitud = app(CotizacionService::class)->solicitarDespacho($this->cotizacion, auth()->user(), app(DespachoService::class));
        $this->cotizacion->refresh();
        $this->notifySuccess('Solicitud de despacho '.$solicitud->numero.' generada.');
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

    public function marcarCotizadaSinEnvio(): void
    {
        app(CotizacionService::class)->marcarCotizadaSinEnvio($this->cotizacion, auth()->user());
        $this->cotizacion->refresh();
        $this->notifySuccess('Cotización marcada como cotizada. Descárgala desde "Ver / descargar PDF" para imprimirla.');
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

<div class="flex flex-col gap-6">
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 flex flex-col gap-6">
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
                                <x-select wire:model.live="items.{{ $i }}.inventario_id" :reset-key="'inv-'.$item['uid']">
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

                    @if (($item['tipo_item'] ?? 'servicio') === 'insumo')
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm items-center">
                            <div class="sm:col-span-2 flex items-center gap-4">
                                <label class="flex items-center gap-1.5">
                                    <input type="radio" value="inventario" wire:model.live="items.{{ $i }}.origen"> Sale del almacén
                                </label>
                                <label class="flex items-center gap-1.5">
                                    <input type="radio" value="compra_externa" wire:model.live="items.{{ $i }}.origen"> Compra externa
                                </label>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Precio (solo esta cotización)</label>
                                <x-input-money :wire-model="'items.'.$i.'.costo_unitario'" />
                            </div>
                        </div>

                        @if (($item['origen'] ?? 'inventario') === 'compra_externa')
                            <div class="flex items-center justify-between gap-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg px-3 py-2">
                                <p class="text-xs text-amber-700 dark:text-amber-300">Compra externa: no se descuenta del inventario. Deja un comentario de control interno en el hilo de comunicación (no se refleja en la cotización).</p>
                                <button wire:click="sugerirComentarioControl({{ $i }})" type="button" class="shrink-0 text-xs font-semibold text-brand-blue hover:underline">Sugerir comentario</button>
                            </div>
                        @endif
                    @endif
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
                            <td class="py-2">
                                {{ $d->nombreItem() }}
                                @if ($d->esCompraExterna())
                                    <span class="ml-1.5 inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Compra externa</span>
                                @endif
                            </td>
                            <td class="py-2 text-right">{{ rtrim(rtrim(number_format((float) $d->cantidad, 2), '0'), '.') }} × {{ \App\Support\Moneda::cop($d->costo_unitario) }}</td>
                            <td class="py-2 text-right font-semibold">{{ \App\Support\Moneda::cop($d->valor_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <dl class="ml-auto w-full sm:w-80 grid grid-cols-2 gap-y-1 text-sm">
            <dt class="text-slate-500">Subtotal</dt>
            <dd class="text-right">{{ \App\Support\Moneda::cop($cotizacion->subtotal) }}</dd>
            <dt class="text-slate-500">Descuento ({{ rtrim(rtrim(number_format((float) $cotizacion->descuento_porcentaje, 2), '0'), '.') }}%)</dt>
            <dd class="text-right">− {{ \App\Support\Moneda::cop($cotizacion->descuento_valor) }}</dd>
            <dt class="text-slate-500">IVA {{ rtrim(rtrim(number_format((float) $cotizacion->iva_porcentaje, 2), '0'), '.') }}%</dt>
            <dd class="text-right">{{ \App\Support\Moneda::cop($cotizacion->iva_valor) }}</dd>
            <dt class="font-bold pt-1 border-t border-slate-200 dark:border-slate-700">Total</dt>
            <dd class="text-right font-bold pt-1 border-t border-slate-200 dark:border-slate-700">{{ \App\Support\Moneda::cop($cotizacion->total) }}</dd>
        </dl>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-wrap gap-3">
        <a href="{{ route('cotizaciones.pdf', $cotizacion) }}" target="_blank" class="border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
            Ver / descargar PDF
        </a>
        @if ($cotizacion->estado === 'en_revision')
            <button type="button"
                    x-on:click="Notify.confirmDanger({
                        title: '¿Enviar esta cotización al cliente?',
                        text: 'Se enviará por correo a ' + {{ Js::from($cotizacion->cliente?->nombre ?? 'el cliente asignado') }} + '.',
                        confirmButtonText: 'Sí, enviar',
                    }).then((ok) => ok && $wire.enviar())"
                    class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Enviar cotización
            </button>
            <button type="button"
                    x-on:click="Notify.confirmDanger({
                        title: '¿Marcar como cotizada sin enviar por correo?',
                        text: 'Úsalo cuando la entregas impresa en mano. No se enviará ningún correo.',
                        confirmButtonText: 'Sí, marcar cotizada',
                    }).then((ok) => ok && $wire.marcarCotizadaSinEnvio())"
                    class="border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Marcar cotizada (impresa, sin correo)
            </button>
        @endif
        @if ($cotizacion->estado === 'cotizada')
            <button type="button"
                    x-on:click="Notify.confirmDanger({
                        title: '¿Marcar como aceptada?',
                        text: 'Úsalo cuando el cliente confirmó en persona, por teléfono u otro canal fuera del hilo de correo.',
                        confirmButtonText: 'Sí, marcar aceptada',
                    }).then((ok) => ok && $wire.aceptar())"
                    class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Marcar aceptada
            </button>
            <button type="button"
                    x-on:click="Notify.confirmDanger({
                        title: '¿Marcar como rechazada?',
                        text: 'Se registrará que el cliente rechazó esta cotización.',
                        confirmButtonText: 'Sí, marcar rechazada',
                    }).then((ok) => ok && $wire.rechazar())"
                    class="border border-brand-red text-brand-red hover:bg-brand-red hover:text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Marcar rechazada
            </button>
        @endif
        @if ($puedeReabrir)
            <button type="button"
                    x-on:click="Notify.confirmDanger({
                        title: '¿Reabrir la cotización?',
                        text: 'Podrás editarla y reenviarla. El historial del hilo se conserva.',
                        confirmButtonText: 'Sí, reabrir',
                    }).then((ok) => ok && $wire.reabrir())"
                    class="border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Reabrir para editar
            </button>
        @endif
        @if ($cotizacion->estado === 'aceptada' && $cotizacion->solicitudDespacho)
            <a href="{{ route('despachos.detalle', $cotizacion->solicitudDespacho) }}" wire:navigate class="border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Ver despacho {{ $cotizacion->solicitudDespacho->numero }}
            </a>
        @elseif ($puedeSolicitarDespacho)
            <button type="button"
                    x-on:click="Notify.confirmDanger({
                        title: '¿Solicitar despacho para esta cotización?',
                        text: 'Se creará una solicitud de despacho con el cliente y los insumos de esta cotización.',
                        confirmButtonText: 'Sí, solicitar',
                    }).then((ok) => ok && $wire.solicitarDespacho())"
                    class="border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Solicitar despacho
            </button>
        @endif
        @if ($cotizacion->estado === 'aceptada' && ! $cotizacion->solicitudDespacho)
            <button wire:click="marcarEntregada" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Marcar entregada
            </button>
        @endif
        @if ($cotizacion->estado === 'aceptada' && $cotizacion->solicitudDespacho)
            <p class="w-full text-xs text-slate-400">Esta cotización pasará a "entregada" automáticamente cuando el almacén confirme la entrega del despacho.</p>
        @endif
        @if ($cotizacion->estado === 'entregada')
            <button wire:click="marcarFacturada" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Marcar facturada
            </button>
        @endif
    </div>
        </div>

        <div class="flex flex-col gap-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-4">
                <h2 class="font-bold text-sm">Datos de la cotización</h2>
                @if ($puedeEditar)
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="text-xs font-semibold text-slate-500">Sede (ciudad)</label>
                            <x-select wire:model="sede">
                                @foreach ($sedes as $codigo => $ciudad)
                                    <option value="{{ $codigo }}">{{ $ciudad }} ({{ $codigo }})</option>
                                @endforeach
                            </x-select>
                            @error('sede') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-500">Descuento %</label>
                            <input type="number" step="0.01" min="0" max="100" wire:model="descuentoPorcentaje"
                                   class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2 text-sm outline-none focus:border-brand-blue">
                            @error('descuentoPorcentaje') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-500">Fecha prometida de entrega</label>
                            <x-input-date wire:model="fechaPromesaEntrega" />
                            <p class="text-[11px] text-slate-400 mt-1">Se usa para medir si la entrega llega a tiempo (KPI OTIF).</p>
                            @error('fechaPromesaEntrega') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                        <button wire:click="guardarCabecera" type="button" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                            Guardar datos
                        </button>
                    </div>
                @else
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        Sede: {{ $sedes[$cotizacion->sede] ?? '—' }} · Descuento: {{ rtrim(rtrim(number_format((float) $cotizacion->descuento_porcentaje, 2), '0'), '.') }}%
                        @if ($cotizacion->fecha_promesa_entrega)
                            · Prometida: {{ $cotizacion->fecha_promesa_entrega->format('d/m/Y') }}
                        @endif
                    </p>
                @endif
            </div>

            @if (! $cotizacion->cliente)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-3">
                    <h2 class="font-bold text-sm">Asignar cliente</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">El remitente de este correo no coincide con ningún cliente registrado. Asígnalo manualmente para poder enviar la cotización.</p>
                    <div class="flex flex-col gap-3">
                        <x-select wire:model="clienteIdAsignar">
                            @foreach ($clientes as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                            @endforeach
                        </x-select>
                        @error('clienteIdAsignar') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        <button wire:click="asignarCliente" class="self-start bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">Asignar</button>
                    </div>
                </div>
            @endif

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
    </div>
</div>
