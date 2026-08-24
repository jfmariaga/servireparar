<?php

use App\Enums\RolPrioridad;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layout')] class extends Component
{
    use WithPagination;

    public string $filtroRol = 'todos';
    public string $busqueda = '';

    public bool $mostrarForm = false;
    public ?int $editandoId = null;

    public string $name = '';
    public string $email = '';
    public string $telefono = '';
    public string $estado = 'activo';
    public string $password = '';
    public array $roles = [];

    public string $errorDesactivar = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function with(): array
    {
        return [
            'usuarios' => User::query()
                ->when($this->filtroRol !== 'todos', fn ($q) => $q->role($this->filtroRol))
                ->when($this->busqueda, fn ($q) => $q->where('name', 'like', "%{$this->busqueda}%"))
                ->with('roles')
                ->orderBy('name')
                ->paginate(10),
            'todosLosRoles' => RolPrioridad::ordenados(),
        ];
    }

    public function nuevo(): void
    {
        Gate::authorize('create', User::class);
        $this->reset(['name', 'email', 'telefono', 'editandoId', 'password', 'roles', 'errorDesactivar']);
        $this->estado = 'activo';
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        Gate::authorize('update', User::class);
        $u = User::with('roles')->findOrFail($id);
        $this->editandoId = $u->id;
        $this->name = $u->name;
        $this->email = $u->email;
        $this->telefono = (string) $u->telefono;
        $this->estado = $u->estado;
        $this->roles = $u->roles->pluck('name')->toArray();
        $this->password = '';
        $this->errorDesactivar = '';
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        Gate::authorize($this->editandoId ? 'update' : 'create', User::class);

        $reglas = [
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150|unique:users,email,'.$this->editandoId,
            'telefono' => 'nullable|string|max:20',
            'estado' => 'required|in:activo,inactivo',
            'roles' => 'array',
        ];

        if (! $this->editandoId) {
            $reglas['password'] = 'required|string|min:8';
        }

        $datos = $this->validate($reglas);
        unset($datos['roles']);

        if ($this->editandoId && $this->password) {
            $datos['password'] = $this->password;
        } elseif (! $this->editandoId) {
            $datos['password'] = $this->password;
        }

        $usuario = User::updateOrCreate(['id' => $this->editandoId], $datos);
        $usuario->syncRoles($this->roles);

        $this->mostrarForm = false;
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
    }

    /**
     * Inactivar usuario, protegiendo al último Administrador activo (FR-008).
     */
    public function alternarEstado(int $id): void
    {
        $usuario = User::findOrFail($id);
        $this->errorDesactivar = '';

        if ($usuario->estado === 'activo' && ! Gate::allows('deactivate', $usuario)) {
            $this->errorDesactivar = 'No puedes inactivar al último Administrador activo del sistema.';

            return;
        }

        Gate::authorize('update', User::class);
        $usuario->estado = $usuario->estado === 'activo' ? 'inactivo' : 'activo';
        $usuario->save();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold">Usuarios</h1>
        <button wire:click="nuevo" class="bg-blue-800 hover:bg-blue-900 text-white text-sm px-4 py-2 rounded">
            + Nuevo usuario
        </button>
    </div>

    @if ($errorDesactivar)
        <div class="mb-4 rounded bg-red-50 text-red-700 text-sm px-3 py-2">{{ $errorDesactivar }}</div>
    @endif

    <div class="flex gap-4 mb-4 text-sm">
        <input type="text" wire:model.live.debounce.400ms="busqueda" placeholder="Buscar por nombre..."
               class="border rounded px-3 py-1.5 w-64">
        <select wire:model.live="filtroRol" class="border rounded px-2 py-1.5">
            <option value="todos">Todos los roles</option>
            @foreach ($todosLosRoles as $rol)
                <option value="{{ $rol }}">{{ $rol }}</option>
            @endforeach
        </select>
    </div>

    @if ($mostrarForm)
        <div class="bg-white border rounded-lg p-6 mb-6 shadow-sm">
            <h2 class="font-semibold mb-4">{{ $editandoId ? 'Editar usuario' : 'Nuevo usuario' }}</h2>

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <label class="block font-medium mb-1">Nombre *</label>
                    <input type="text" wire:model="name" class="w-full border rounded px-3 py-2">
                    @error('name') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-medium mb-1">Email *</label>
                    <input type="email" wire:model="email" class="w-full border rounded px-3 py-2">
                    @error('email') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-medium mb-1">Teléfono</label>
                    <input type="text" wire:model="telefono" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block font-medium mb-1">Estado</label>
                    <select wire:model="estado" class="w-full border rounded px-3 py-2">
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>
                <div>
                    <label class="block font-medium mb-1">
                        {{ $editandoId ? 'Nueva contraseña (opcional)' : 'Contraseña *' }}
                    </label>
                    <input type="password" wire:model="password" class="w-full border rounded px-3 py-2">
                    @error('password') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="col-span-2">
                    <label class="block font-medium mb-1">Roles</label>
                    <div class="flex gap-4 flex-wrap">
                        @foreach ($todosLosRoles as $rol)
                            <label class="flex items-center gap-1.5">
                                <input type="checkbox" wire:model="roles" value="{{ $rol }}">
                                {{ $rol }}
                            </label>
                        @endforeach
                    </div>
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
                    <th class="px-4 py-2">Email</th>
                    <th class="px-4 py-2">Roles</th>
                    <th class="px-4 py-2">Estado</th>
                    <th class="px-4 py-2">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($usuarios as $u)
                    <tr class="border-b">
                        <td class="px-4 py-2">{{ $u->name }}</td>
                        <td class="px-4 py-2">{{ $u->email }}</td>
                        <td class="px-4 py-2">{{ $u->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-0.5 rounded text-xs {{ $u->estado === 'activo' ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600' }}">
                                {{ ucfirst($u->estado) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 space-x-2">
                            <button wire:click="editar({{ $u->id }})" class="text-blue-700 hover:underline">Editar</button>
                            <button wire:click="alternarEstado({{ $u->id }})" class="text-slate-600 hover:underline">
                                {{ $u->estado === 'activo' ? 'Inactivar' : 'Activar' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Sin usuarios registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3">{{ $usuarios->links() }}</div>
    </div>
</div>
