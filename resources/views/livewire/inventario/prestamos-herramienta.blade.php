<?php

use App\Livewire\Concerns\Notifies;
use App\Models\Inventario;
use App\Models\PrestamoHerramienta;
use App\Models\Tecnico;
use App\Services\OrdenTrabajo\PrestamoHerramientaService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Préstamos de herramienta'])] class extends Component
{
    use Notifies;

    #[Url]
    public string $estado = 'solicitada';

    public ?int $rechazandoId = null;
    public string $motivoRechazo = '';
    public ?int $devolviendoId = null;
    public string $estadoDevolucion = 'disponible';

    // Prestar directamente (el Almacenista elige técnico + herramienta, sin que el técnico la pida antes)
    public ?int $prestarTecnicoId = null;
    public ?int $prestarHerramientaId = null;

    public function mount(): void
    {
        abort_unless(
            Gate::allows('attend-ot-insumo') || Gate::allows('manage-ot') || auth()->user()->tecnico,
            403,
        );
    }

    private function soloMisPrestamos(): bool
    {
        return ! Gate::allows('attend-ot-insumo') && ! Gate::allows('manage-ot') && auth()->user()->tecnico;
    }

    public function updatingEstado(): void
    {
        $this->rechazandoId = null;
        $this->devolviendoId = null;
    }

    private function puedeAtender(): bool
    {
        return Gate::allows('attend-ot-insumo');
    }

    public function with(): array
    {
        $base = fn () => PrestamoHerramienta::with([
            'inventario:id,nombre,codigo,estado_herramienta',
            'tecnico.usuario:id,name',
            'ordenTrabajo:id,numero_ot',
        ]);

        $soloMis = $this->soloMisPrestamos();
        $miTecnicoId = auth()->user()->tecnico?->id;

        return [
            'puedeAtender' => $this->puedeAtender(),
            'soloMis' => $soloMis,
            'tecnicosActivos' => $this->puedeAtender()
                ? Tecnico::disponibles()->with('usuario:id,name')->get()
                    ->map(fn (Tecnico $t) => ['id' => $t->id, 'nombre' => $t->usuario?->name ?? 'Técnico #'.$t->id])
                : collect(),
            'herramientasDisponibles' => $this->puedeAtender()
                ? Inventario::activos()->where('tipo', 'herramienta')->where('estado_herramienta', 'disponible')
                    ->orderBy('nombre')->get(['id', 'nombre', 'codigo'])
                : collect(),
            'prestamos' => $base()
                ->when($soloMis, fn ($q) => $q->where('tecnico_id', $miTecnicoId))
                ->when($this->estado !== 'todos', fn ($q) => $q->where('estado', $this->estado))
                ->latest('id')->paginate(15),
            'conteos' => PrestamoHerramienta::when($soloMis, fn ($q) => $q->where('tecnico_id', $miTecnicoId))
                ->selectRaw('estado, count(*) c')->groupBy('estado')->pluck('c', 'estado'),
            'porTecnico' => $soloMis ? collect() : PrestamoHerramienta::sinDevolver()
                ->with(['inventario:id,nombre', 'tecnico.usuario:id,name'])
                ->get()
                ->groupBy(fn ($p) => $p->tecnico?->usuario?->name ?? 'Técnico #'.$p->tecnico_id),
        ];
    }

    public function prestar(PrestamoHerramientaService $svc): void
    {
        Gate::authorize('attend-ot-insumo');
        $datos = $this->validate([
            'prestarTecnicoId' => 'required|exists:tecnicos,id',
            'prestarHerramientaId' => 'required|exists:inventario,id',
        ], [], ['prestarTecnicoId' => 'técnico', 'prestarHerramientaId' => 'herramienta']);

        try {
            $svc->prestar(
                Tecnico::findOrFail($datos['prestarTecnicoId']),
                Inventario::findOrFail($datos['prestarHerramientaId']),
                auth()->user(),
            );
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        $this->reset('prestarTecnicoId', 'prestarHerramientaId');
        $this->notifySuccess('Herramienta prestada.');
    }

    public function entregar(int $id, PrestamoHerramientaService $svc): void
    {
        Gate::authorize('attend-ot-insumo');
        try {
            $svc->entregar(PrestamoHerramienta::findOrFail($id), auth()->user());
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }
        $this->notifySuccess('Herramienta entregada al técnico.');
    }

    public function rechazar(PrestamoHerramientaService $svc): void
    {
        Gate::authorize('attend-ot-insumo');
        $this->validate(['motivoRechazo' => 'required|string|max:500'], [], ['motivoRechazo' => 'motivo']);
        try {
            $svc->rechazar(PrestamoHerramienta::findOrFail($this->rechazandoId), auth()->user(), $this->motivoRechazo);
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }
        $this->rechazandoId = null;
        $this->motivoRechazo = '';
        $this->notifySuccess('Préstamo rechazado.');
    }

    public function registrarDevolucion(PrestamoHerramientaService $svc): void
    {
        Gate::authorize('attend-ot-insumo');
        try {
            $svc->registrarDevolucion(PrestamoHerramienta::findOrFail($this->devolviendoId), auth()->user(), $this->estadoDevolucion);
        } catch (ValidationException $e) {
            $this->notifyError($e->getMessage());

            return;
        }
        $this->devolviendoId = null;
        $this->estadoDevolucion = 'disponible';
        $this->notifySuccess('Devolución registrada.');
    }
}; ?>

<div class="w-full flex flex-col gap-5">
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-lg font-bold font-display">Préstamos de herramienta</h1>
        <p class="text-sm text-slate-400">Bodega presta la herramienta directamente al técnico y registra la devolución al recibirla.</p>
    </div>

    @if ($puedeAtender)
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 flex flex-col gap-3">
            <h2 class="font-bold text-sm">Prestar herramienta</h2>
            <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                <x-field label="Técnico" class="flex-1">
                    <x-select wire:model="prestarTecnicoId" :reset-key="'prestar-tec'">
                        @foreach ($tecnicosActivos as $t)<option value="{{ $t['id'] }}">{{ $t['nombre'] }}</option>@endforeach
                    </x-select>
                    @error('prestarTecnicoId') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                </x-field>
                <x-field label="Herramienta" class="flex-1">
                    <x-select wire:model="prestarHerramientaId" :reset-key="'prestar-herr'">
                        @foreach ($herramientasDisponibles as $h)<option value="{{ $h->id }}">{{ $h->nombre }} ({{ $h->codigo }})</option>@endforeach
                    </x-select>
                    @error('prestarHerramientaId') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                </x-field>
                <button wire:click="prestar" class="h-11 mt-auto px-5 rounded-xl bg-brand-blue hover:bg-brand-blue-dark text-white text-[12.5px] font-semibold shrink-0">Prestar</button>
            </div>
        </div>
    @endif

    <div class="flex flex-wrap gap-2">
        @foreach (['solicitada' => 'Solicitadas', 'entregada' => 'En préstamo', 'devuelta' => 'Devueltas', 'rechazada' => 'Rechazadas', 'todos' => 'Todos'] as $k => $label)
            <button wire:click="$set('estado', '{{ $k }}')"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[12.5px] font-semibold transition
                           {{ $estado === $k ? 'bg-brand-blue text-white' : 'border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                {{ $label }}
                @if ($k !== 'todos' && ($conteos[$k] ?? 0) > 0)
                    <span class="rounded-full {{ $estado === $k ? 'bg-white/20' : 'bg-slate-100 dark:bg-slate-800' }} px-1.5 text-[10px]">{{ $conteos[$k] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                <tr>
                    <th class="px-4 py-3 font-semibold">Técnico</th>
                    <th class="px-4 py-3 font-semibold">Herramienta</th>
                    <th class="px-4 py-3 font-semibold">OT</th>
                    <th class="px-4 py-3 font-semibold">Solicitada</th>
                    <th class="px-4 py-3 font-semibold">Estado</th>
                    <th class="px-4 py-3 font-semibold">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prestamos as $p)
                    <tr wire:key="prh-{{ $p->id }}" class="border-b border-slate-50 dark:border-slate-800/60 align-top">
                        <td class="px-4 py-3 whitespace-nowrap">{{ $p->tecnico?->usuario?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $p->inventario?->nombre }} <span class="text-xs text-slate-400">({{ $p->inventario?->codigo }})</span></td>
                        <td class="px-4 py-3">{{ $p->ordenTrabajo?->numero_ot ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400">{{ $p->solicitada_en?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold
                                {{ $p->estado === 'entregada' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : ($p->estado === 'devuelta' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : ($p->estado === 'rechazada' ? 'bg-red-100 text-brand-red dark:bg-red-900/30' : 'bg-slate-100 dark:bg-slate-800')) }}">
                                {{ ucfirst($p->estado) }}
                            </span>
                            @if ($p->estado === 'rechazada' && $p->motivo_rechazo)
                                <div class="text-xs text-brand-red mt-1">{{ $p->motivo_rechazo }}</div>
                            @endif
                            @if ($p->estado === 'devuelta' && $p->estado_devolucion)
                                <div class="text-xs text-slate-400 mt-1">{{ str($p->estado_devolucion)->replace('_', ' ') }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if (! $puedeAtender)
                                <span class="text-xs text-slate-400">—</span>
                            @elseif ($p->estado === 'solicitada')
                                @if ($rechazandoId === $p->id)
                                    <div class="flex flex-col gap-2 w-56">
                                        <input type="text" wire:model="motivoRechazo" placeholder="Motivo del rechazo" class="h-9 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 px-3 text-xs outline-none focus:border-brand-blue focus:ring-4 focus:ring-brand-blue/10">
                                        <div class="flex gap-2">
                                            <button wire:click="rechazar" class="text-[12px] font-semibold px-3 py-1.5 rounded-lg border border-brand-red text-brand-red">Confirmar</button>
                                            <button wire:click="$set('rechazandoId', null)" class="text-[12px] px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700">Cancelar</button>
                                        </div>
                                        @error('motivoRechazo') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                                    </div>
                                @else
                                    <div class="flex flex-wrap gap-2">
                                        <button wire:click="entregar({{ $p->id }})" class="text-[12px] font-semibold px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Entregar</button>
                                        <button wire:click="$set('rechazandoId', {{ $p->id }})" class="text-[12px] px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-400 hover:text-brand-red">Rechazar</button>
                                    </div>
                                @endif
                            @elseif ($p->estado === 'entregada')
                                @if ($devolviendoId === $p->id)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <select wire:model="estadoDevolucion" class="h-8 rounded-lg border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs px-2">
                                            <option value="disponible">Disponible</option>
                                            <option value="dañada">Dañada</option>
                                            <option value="en_mantenimiento">En mantenimiento</option>
                                        </select>
                                        <button wire:click="registrarDevolucion" class="text-[11px] font-semibold px-2.5 py-1 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Confirmar</button>
                                        <button wire:click="$set('devolviendoId', null)" class="text-[11px] px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700">Cancelar</button>
                                    </div>
                                @else
                                    <button wire:click="$set('devolviendoId', {{ $p->id }})" class="text-[12px] font-semibold px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Registrar devolución</button>
                                @endif
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Sin préstamos en este estado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $prestamos->links() }}</div>

    {{-- Herramientas por técnico sin devolver (T130) --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-3 text-sm" @if ($soloMis) hidden @endif>
        <h2 class="font-bold text-sm">Herramientas por técnico (sin devolver)</h2>
        @forelse ($porTecnico as $nombre => $items)
            <div wire:key="pt-{{ $loop->index }}" class="border-b border-slate-50 dark:border-slate-800/60 pb-2 last:border-0">
                <p class="font-semibold">{{ $nombre }}</p>
                <ul class="text-xs text-slate-500 dark:text-slate-400 list-disc ml-4">
                    @foreach ($items as $it)
                        <li>{{ $it->inventario?->nombre }} — desde {{ $it->solicitada_en?->format('d/m/Y') }}</li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="text-xs text-slate-400">Ningún técnico tiene herramientas en préstamo.</p>
        @endforelse
    </div>
</div>
