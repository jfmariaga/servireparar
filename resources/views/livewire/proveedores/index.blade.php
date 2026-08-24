<?php

use App\Models\Proveedor;
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
    public string $nit = '';
    public string $correo = '';
    public string $direccion = '';
    public string $estado = 'activo';
    public string $advertenciaDuplicado = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Proveedor::class);
    }

    public function with(): array
    {
        return [
            'proveedores' => Proveedor::query()
                ->when($this->filtroEstado !== 'todos', fn ($q) => $q->where('estado', $this->filtroEstado))
                ->when($this->busqueda, fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%"))
                ->orderBy('nombre')
                ->paginate(10),
        ];
    }

    public function nuevo(): void
    {
        Gate::authorize('create', Proveedor::class);
        $this->reset(['nombre', 'nit', 'correo', 'direccion', 'editandoId', 'advertenciaDuplicado']);
        $this->estado = 'activo';
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        Gate::authorize('update', Proveedor::class);
        $p = Proveedor::findOrFail($id);
        $this->editandoId = $p->id;
        $this->nombre = $p->nombre;
        $this->nit = (string) $p->nit;
        $this->correo = (string) $p->correo;
        $this->direccion = (string) $p->direccion;
        $this->estado = $p->estado;
        $this->advertenciaDuplicado = '';
        $this->mostrarForm = true;
    }

    public function verificarDuplicado(): void
    {
        $this->advertenciaDuplicado = '';

        if ($this->nit && Proveedor::where('nit', $this->nit)->when($this->editandoId, fn ($q) => $q->where('id', '!=', $this->editandoId))->exists()) {
            $this->advertenciaDuplicado = "Ya existe un proveedor registrado con el NIT {$this->nit}.";

            return;
        }

        if ($this->correo && Proveedor::where('correo', $this->correo)->when($this->editandoId, fn ($q) => $q->where('id', '!=', $this->editandoId))->exists()) {
            $this->advertenciaDuplicado = "Ya existe un proveedor registrado con el correo {$this->correo}.";
        }
    }

    public function guardar(): void
    {
        Gate::authorize($this->editandoId ? 'update' : 'create', Proveedor::class);
        $this->verificarDuplicado();

        $datos = $this->validate([
            'nombre' => 'required|string|max:150',
            'nit' => 'nullable|string|max:20',
            'correo' => 'nullable|email|max:150',
            'direccion' => 'nullable|string|max:255',
            'estado' => 'required|in:activo,inactivo',
        ]);

        Proveedor::updateOrCreate(['id' => $this->editandoId], $datos);
        $this->mostrarForm = false;
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
    }

    public function alternarEstado(int $id): void
    {
        Gate::authorize('update', Proveedor::class);
        $p = Proveedor::findOrFail($id);
        $p->estado = $p->estado === 'activo' ? 'inactivo' : 'activo';
        $p->save();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold">Proveedores</h1>
        <button wire:click="nuevo" class="bg-blue-800 hover:bg-blue-900 text-white text-sm px-4 py-2 rounded">
            + Nuevo proveedor
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
            <h2 class="font-semibold mb-4">{{ $editandoId ? 'Editar proveedor' : 'Nuevo proveedor' }}</h2>

            @if ($advertenciaDuplicado)
                <div class="mb-4 rounded bg-amber-50 text-amber-800 text-sm px-3 py-2">
                    ⚠ {{ $advertenciaDuplicado }} Puedes continuar si confirmas que es un registro distinto.
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="col-span-2">
                    <label class="block font-medium mb-1">Nombre *</label>
                    <input type="text" wire:model="nombre" class="w-full border rounded px-3 py-2">
                    @error('nombre') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-medium mb-1">NIT</label>
                    <input type="text" wire:model.blur="nit" wire:blur="verificarDuplicado" class="w-full border rounded px-3 py-2">
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
                <div class="col-span-2">
                    <label class="block font-medium mb-1">Dirección</label>
                    <input type="text" wire:model="direccion" class="w-full border rounded px-3 py-2">
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
                    <th class="px-4 py-2">NIT</th>
                    <th class="px-4 py-2">Correo</th>
                    <th class="px-4 py-2">Estado</th>
                    <th class="px-4 py-2">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($proveedores as $p)
                    <tr class="border-b">
                        <td class="px-4 py-2">{{ $p->nombre }}</td>
                        <td class="px-4 py-2">{{ $p->nit ?: '—' }}</td>
                        <td class="px-4 py-2">{{ $p->correo ?: '—' }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-0.5 rounded text-xs {{ $p->estado === 'activo' ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600' }}">
                                {{ ucfirst($p->estado) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 space-x-2">
                            <button wire:click="editar({{ $p->id }})" class="text-blue-700 hover:underline">Editar</button>
                            <button wire:click="alternarEstado({{ $p->id }})" class="text-slate-600 hover:underline">
                                {{ $p->estado === 'activo' ? 'Inactivar' : 'Activar' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Sin proveedores registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3">{{ $proveedores->links() }}</div>
    </div>
</div>
