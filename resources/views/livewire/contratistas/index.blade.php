<?php

use App\Models\Contratista;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layout')] class extends Component
{
    use WithPagination;

    public string $filtroEstado = 'activo';
    public string $busqueda = '';
    public bool $mostrarForm = false;
    public ?int $editandoId = null;

    public string $nombre = '';
    public string $especialidad = '';
    public string $telefono = '';
    public string $correo = '';
    public string $estado = 'activo';
    public string $advertenciaDuplicado = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Contratista::class);
    }

    public function with(): array
    {
        return [
            'contratistas' => Contratista::query()
                ->when($this->filtroEstado !== 'todos', fn ($q) => $q->where('estado', $this->filtroEstado))
                ->when($this->busqueda, fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%"))
                ->orderBy('nombre')
                ->paginate(10),
        ];
    }

    public function nuevo(): void
    {
        Gate::authorize('create', Contratista::class);
        $this->reset(['nombre', 'especialidad', 'telefono', 'correo', 'editandoId', 'advertenciaDuplicado']);
        $this->estado = 'activo';
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        Gate::authorize('update', Contratista::class);
        $c = Contratista::findOrFail($id);
        $this->editandoId = $c->id;
        $this->nombre = $c->nombre;
        $this->especialidad = (string) $c->especialidad;
        $this->telefono = (string) $c->telefono;
        $this->correo = (string) $c->correo;
        $this->estado = $c->estado;
        $this->advertenciaDuplicado = '';
        $this->mostrarForm = true;
    }

    public function verificarDuplicado(): void
    {
        $this->advertenciaDuplicado = '';

        if ($this->correo && Contratista::where('correo', $this->correo)->when($this->editandoId, fn ($q) => $q->where('id', '!=', $this->editandoId))->exists()) {
            $this->advertenciaDuplicado = "Ya existe un contratista registrado con el correo {$this->correo}.";
        }
    }

    public function guardar(): void
    {
        Gate::authorize($this->editandoId ? 'update' : 'create', Contratista::class);
        $this->verificarDuplicado();

        $datos = $this->validate([
            'nombre' => 'required|string|max:150',
            'especialidad' => 'nullable|string|max:100',
            'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|max:150',
            'estado' => 'required|in:activo,inactivo',
        ]);

        Contratista::updateOrCreate(['id' => $this->editandoId], $datos);
        $this->mostrarForm = false;
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
    }

    public function alternarEstado(int $id): void
    {
        Gate::authorize('update', Contratista::class);
        $c = Contratista::findOrFail($id);
        $c->estado = $c->estado === 'activo' ? 'inactivo' : 'activo';
        $c->save();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold">Contratistas</h1>
        <button wire:click="nuevo" class="bg-blue-800 hover:bg-blue-900 text-white text-sm px-4 py-2 rounded">
            + Nuevo contratista
        </button>
    </div>

    <div class="flex gap-4 mb-4 text-sm">
        <input type="text" wire:model.live.debounce.400ms="busqueda" placeholder="Buscar por nombre..."
               class="border rounded px-3 py-1.5 w-64">
        <select wire:model.live="filtroEstado" class="border rounded px-2 py-1.5">
            <option value="activo">Activos</option>
            <option value="inactivo">Inactivos</option>
            <option value="todos">Todos</option>
        </select>
    </div>

    @if ($mostrarForm)
        <div class="bg-white border rounded-lg p-6 mb-6 shadow-sm">
            <h2 class="font-semibold mb-4">{{ $editandoId ? 'Editar contratista' : 'Nuevo contratista' }}</h2>

            @if ($advertenciaDuplicado)
                <div class="mb-4 rounded bg-amber-50 text-amber-800 text-sm px-3 py-2">
                    ⚠ {{ $advertenciaDuplicado }} Puedes continuar si confirmas que es un registro distinto.
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="col-span-2">
                    <label class="block font-medium mb-1">Nombre / Empresa *</label>
                    <input type="text" wire:model="nombre" class="w-full border rounded px-3 py-2">
                    @error('nombre') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-medium mb-1">Especialidad</label>
                    <input type="text" wire:model="especialidad" class="w-full border rounded px-3 py-2" placeholder="Ej. Calcomanías, soldadura...">
                </div>
                <div>
                    <label class="block font-medium mb-1">Teléfono</label>
                    <input type="text" wire:model="telefono" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block font-medium mb-1">Correo</label>
                    <input type="email" wire:model.blur="correo" wire:blur="verificarDuplicado" class="w-full border rounded px-3 py-2">
                    @error('correo') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-medium mb-1">Estado</label>
                    <select wire:model="estado" class="w-full border rounded px-3 py-2">
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-2 mt-6">
                <button wire:click="guardar" class="bg-blue-800 hover:bg-blue-900 text-white text-sm px-4 py-2 rounded">Guardar</button>
                <button wire:click="cancelar" class="text-sm px-4 py-2 rounded border">Cancelar</button>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-800 text-white text-left">
                <tr>
                    <th class="px-4 py-2">Nombre</th>
                    <th class="px-4 py-2">Especialidad</th>
                    <th class="px-4 py-2">Correo</th>
                    <th class="px-4 py-2">Estado</th>
                    <th class="px-4 py-2">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contratistas as $c)
                    <tr class="border-b">
                        <td class="px-4 py-2">{{ $c->nombre }}</td>
                        <td class="px-4 py-2">{{ $c->especialidad ?: '—' }}</td>
                        <td class="px-4 py-2">{{ $c->correo ?: '—' }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-0.5 rounded text-xs {{ $c->estado === 'activo' ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600' }}">
                                {{ ucfirst($c->estado) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 space-x-2">
                            <button wire:click="editar({{ $c->id }})" class="text-blue-700 hover:underline">Editar</button>
                            <button wire:click="alternarEstado({{ $c->id }})" class="text-slate-600 hover:underline">
                                {{ $c->estado === 'activo' ? 'Inactivar' : 'Activar' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Sin contratistas registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3">{{ $contratistas->links() }}</div>
    </div>
</div>
