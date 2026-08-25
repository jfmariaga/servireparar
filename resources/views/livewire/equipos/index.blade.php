<?php

use App\Livewire\Concerns\Notifies;
use App\Models\Cliente;
use App\Models\Equipo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layout', ['title' => 'Equipos'])] class extends Component
{
    use Notifies, WithPagination;

    public string $filtroEstado = 'todos';
    public string $busqueda = '';

    public bool $mostrarForm = false;
    public ?int $editandoId = null;

    public ?int $clienteId = null;
    public string $tipo = '';
    public string $marca = '';
    public string $modelo = '';
    public string $serie = '';
    public string $ubicacion = '';
    public string $estado = 'operativo';
    public string $periodicidadDias = '';

    public const ESTADOS = [
        'operativo' => 'Operativo',
        'en_reparacion' => 'En reparación',
        'fuera_de_servicio' => 'Fuera de servicio',
        'de_baja' => 'De baja',
    ];

    public function mount(): void
    {
        Gate::authorize('viewAny', Equipo::class);
    }

    public function with(): array
    {
        return [
            'equipos' => Equipo::query()
                ->when($this->filtroEstado !== 'todos', fn ($q) => $q->where('estado', $this->filtroEstado))
                ->when($this->busqueda, fn ($q) => $q->where(fn ($q2) => $q2
                    ->where('tipo', 'like', "%{$this->busqueda}%")
                    ->orWhere('marca', 'like', "%{$this->busqueda}%")
                    ->orWhere('serie', 'like', "%{$this->busqueda}%")))
                ->with('cliente')
                ->orderByDesc('id')
                ->paginate(10),
            'clientes' => Cliente::activos()->orderBy('nombre')->get(),
            'estados' => self::ESTADOS,
        ];
    }

    public function nuevo(): void
    {
        Gate::authorize('create', Equipo::class);
        $this->reset(['clienteId', 'tipo', 'marca', 'modelo', 'serie', 'ubicacion', 'editandoId', 'periodicidadDias']);
        $this->estado = 'operativo';
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        Gate::authorize('update', Equipo::class);
        $equipo = Equipo::findOrFail($id);
        $this->editandoId = $equipo->id;
        $this->clienteId = $equipo->cliente_id;
        $this->tipo = $equipo->tipo;
        $this->marca = (string) $equipo->marca;
        $this->modelo = (string) $equipo->modelo;
        $this->serie = (string) $equipo->serie;
        $this->ubicacion = (string) $equipo->ubicacion;
        $this->estado = $equipo->estado;
        $this->periodicidadDias = $equipo->periodicidad_mantenimiento_dias !== null
            ? (string) $equipo->periodicidad_mantenimiento_dias
            : '';
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        Gate::authorize($this->editandoId ? 'update' : 'create', Equipo::class);

        $datos = $this->validate([
            'clienteId' => 'required|exists:clientes,id',
            'tipo' => 'required|string|max:100',
            'marca' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
            'serie' => 'nullable|string|max:100',
            'ubicacion' => 'nullable|string|max:150',
            'estado' => 'required|in:'.implode(',', array_keys(self::ESTADOS)),
            'periodicidadDias' => 'nullable|integer|min:1',
        ]);

        Equipo::updateOrCreate(['id' => $this->editandoId], [
            'cliente_id' => $datos['clienteId'],
            'tipo' => $datos['tipo'],
            'marca' => $datos['marca'],
            'modelo' => $datos['modelo'],
            'serie' => $datos['serie'],
            'ubicacion' => $datos['ubicacion'],
            'estado' => $datos['estado'],
            'periodicidad_mantenimiento_dias' => $datos['periodicidadDias'] !== '' ? $datos['periodicidadDias'] : null,
        ]);

        $esNuevo = ! $this->editandoId;
        $this->mostrarForm = false;
        $this->notifySuccess($esNuevo ? 'Equipo registrado correctamente.' : 'Equipo actualizado correctamente.');
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
    }
}; ?>

<div>
    <div class="flex items-center justify-end mb-6">
        <x-icon-button wire:click="nuevo" title="Nuevo equipo" variant="primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
        </x-icon-button>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 mb-5 text-sm">
        <div class="relative flex-1 sm:flex-none">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" wire:model.live.debounce.400ms="busqueda" placeholder="Buscar por tipo, marca o serie..."
                   class="border border-slate-200 dark:border-slate-700 dark:bg-slate-900 rounded-lg pl-9 pr-3 py-2 w-full sm:w-72 outline-none focus:border-brand-blue">
        </div>
        <div class="w-full sm:w-56">
            <x-select wire:model.live="filtroEstado" :placeholder="null">
                <option value="todos">Todos los estados</option>
                @foreach ($estados as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                @endforeach
            </x-select>
        </div>
    </div>

    @if ($mostrarForm)
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 mb-6">
            <h2 class="font-bold mb-4">{{ $editandoId ? 'Editar equipo' : 'Nuevo equipo' }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Cliente *</label>
                    <x-select wire:model="clienteId" :reset-key="'cliente-'.($editandoId ?? 'nuevo')">
                        @foreach ($clientes as $cliente)
                            <option value="{{ $cliente->id }}">{{ $cliente->nombre }}</option>
                        @endforeach
                    </x-select>
                    @error('clienteId') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Tipo *</label>
                    <input type="text" wire:model="tipo" placeholder="Ej. Aire acondicionado" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('tipo') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Marca</label>
                    <input type="text" wire:model="marca" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Modelo</label>
                    <input type="text" wire:model="modelo" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Serie</label>
                    <input type="text" wire:model="serie" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Ubicación</label>
                    <input type="text" wire:model="ubicacion" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Estado</label>
                    <x-select wire:model="estado" :placeholder="null" :reset-key="'estado-'.($editandoId ?? 'nuevo')">
                        @foreach ($estados as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Mantenimiento preventivo cada (días)</label>
                    <input type="number" min="1" wire:model="periodicidadDias" placeholder="Opcional" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('periodicidadDias') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                    <p class="text-xs text-slate-400 mt-1">Déjalo vacío si el equipo no requiere mantenimiento programado.</p>
                </div>
            </div>

            <div class="flex gap-2 mt-6">
                <button wire:click="guardar" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">Guardar</button>
                <button wire:click="cancelar" class="text-[13.5px] font-semibold px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Cancelar</button>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Tipo</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Marca / Modelo</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Serie</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Cliente</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Estado</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($equipos as $equipo)
                        <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-5 py-3 font-medium whitespace-nowrap">{{ $equipo->tipo }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ trim(($equipo->marca ?? '').' '.($equipo->modelo ?? '')) ?: '—' }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $equipo->serie ?: '—' }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $equipo->cliente->nombre }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'inline-block px-2.5 py-1 rounded-full text-[11.5px] font-semibold whitespace-nowrap',
                                    'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10' => $equipo->estado === 'operativo',
                                    'bg-amber-50 text-amber-600 dark:bg-amber-500/10' => $equipo->estado === 'en_reparacion',
                                    'bg-slate-100 text-slate-500 dark:bg-slate-800' => in_array($equipo->estado, ['fuera_de_servicio', 'de_baja']),
                                ])>
                                    {{ $estados[$equipo->estado] ?? $equipo->estado }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <x-icon-button wire:click="editar({{ $equipo->id }})" title="Editar equipo">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 17h4l10-10-4-4L4 13v4z"/></svg>
                                </x-icon-button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">Sin equipos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3.5">{{ $equipos->links() }}</div>
    </div>
</div>
