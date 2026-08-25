<?php

use App\Enums\RolPrioridad;
use App\Livewire\Concerns\Notifies;
use App\Models\Especialidad;
use App\Models\Tecnico;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layout', ['title' => 'Usuarios'])] class extends Component
{
    use Notifies, WithPagination;

    public string $filtroRol = 'todos';
    public string $filtroEstado = 'activo';
    public string $busqueda = '';

    public bool $mostrarForm = false;
    public ?int $editandoId = null;

    public string $name = '';
    public string $email = '';
    public string $telefono = '';
    public string $estado = 'activo';
    public string $password = '';
    public array $roles = [];

    // Ficha de técnico (spec 004) — solo aplica cuando el rol Técnico está marcado.
    public ?int $especialidadId = null;
    public string $tarifaHora = '';
    public bool $tecnicoActivo = true;

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
                ->when($this->filtroEstado !== 'todos', fn ($q) => $q->where('estado', $this->filtroEstado))
                ->when($this->busqueda, fn ($q) => $q->where('name', 'like', "%{$this->busqueda}%"))
                ->with(['roles', 'tecnico.especialidad'])
                ->orderBy('name')
                ->paginate(10),
            'todosLosRoles' => RolPrioridad::ordenados(),
            'especialidades' => Especialidad::activas()->orderBy('nombre')->get(),
        ];
    }

    public function esTecnico(): bool
    {
        return in_array(RolPrioridad::Tecnico->value, $this->roles, true);
    }

    public function nuevo(): void
    {
        Gate::authorize('create', User::class);
        $this->reset([
            'name', 'email', 'telefono', 'editandoId', 'password', 'roles', 'errorDesactivar',
            'especialidadId', 'tarifaHora',
        ]);
        $this->estado = 'activo';
        $this->tecnicoActivo = true;
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        Gate::authorize('update', User::class);
        $u = User::with(['roles', 'tecnico'])->findOrFail($id);
        $this->editandoId = $u->id;
        $this->name = $u->name;
        $this->email = $u->email;
        $this->telefono = (string) $u->telefono;
        $this->estado = $u->estado;
        $this->roles = $u->roles->pluck('name')->toArray();
        $this->password = '';
        $this->errorDesactivar = '';

        $this->especialidadId = $u->tecnico?->especialidad_id;
        $this->tarifaHora = $u->tecnico?->tarifa_hora !== null ? (string) $u->tecnico->tarifa_hora : '';
        $this->tecnicoActivo = $u->tecnico?->activo ?? true;

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
            'especialidadId' => [Rule::requiredIf($this->esTecnico()), 'nullable', 'exists:especialidades,id'],
            'tarifaHora' => 'nullable|numeric|min:0',
        ];

        if (! $this->editandoId) {
            $reglas['password'] = 'required|string|min:8';
        }

        $datos = $this->validate($reglas);
        unset($datos['roles'], $datos['especialidadId'], $datos['tarifaHora']);

        if ($this->editandoId && $this->password) {
            $datos['password'] = $this->password;
        } elseif (! $this->editandoId) {
            $datos['password'] = $this->password;
        }

        $esNuevo = ! $this->editandoId;

        $usuario = User::updateOrCreate(['id' => $this->editandoId], $datos);
        $usuario->syncRoles($this->roles);

        if ($this->esTecnico()) {
            Gate::authorize('manage', Tecnico::class);

            Tecnico::updateOrCreate(
                ['usuario_id' => $usuario->id],
                [
                    'especialidad_id' => $this->especialidadId,
                    'tarifa_hora' => $this->tarifaHora !== '' ? $this->tarifaHora : null,
                    'activo' => $this->tecnicoActivo,
                ]
            );
        }

        $this->mostrarForm = false;
        $this->notifySuccess($esNuevo ? 'Usuario creado correctamente.' : 'Usuario actualizado correctamente.');
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
            $this->notifyError($this->errorDesactivar);

            return;
        }

        Gate::authorize('update', User::class);
        $usuario->estado = $usuario->estado === 'activo' ? 'inactivo' : 'activo';
        $usuario->save();
        $this->notifySuccess($usuario->estado === 'activo' ? 'Usuario activado.' : 'Usuario inactivado.');
    }
}; ?>

<div>
    @include('partials.usuarios-tabs')

    <div class="flex items-center justify-end mb-6">
        <x-icon-button wire:click="nuevo" title="Nuevo usuario" variant="primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
        </x-icon-button>
    </div>

    @if ($errorDesactivar)
        <div class="mb-4 rounded-lg bg-brand-red-tint text-brand-red text-sm px-3.5 py-2.5">{{ $errorDesactivar }}</div>
    @endif

    <div class="flex flex-col sm:flex-row gap-3 mb-5 text-sm">
        <div class="relative flex-1 sm:flex-none">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" wire:model.live.debounce.400ms="busqueda" placeholder="Buscar por nombre..."
                   class="border border-slate-200 dark:border-slate-700 dark:bg-slate-900 rounded-lg pl-9 pr-3 py-2 w-full sm:w-64 outline-none focus:border-brand-blue">
        </div>
        <div class="w-full sm:w-56">
            <x-select wire:model.live="filtroRol" :placeholder="null">
                <option value="todos">Todos los roles</option>
                @foreach ($todosLosRoles as $rol)
                    <option value="{{ $rol }}">{{ $rol }}</option>
                @endforeach
            </x-select>
        </div>
        <div class="w-full sm:w-48">
            <x-select wire:model.live="filtroEstado" :placeholder="null">
                <option value="activo">Activos</option>
                <option value="inactivo">Inactivos</option>
                <option value="todos">Todos</option>
            </x-select>
        </div>
    </div>

    @if ($mostrarForm)
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 mb-6">
            <h2 class="font-bold mb-4">{{ $editandoId ? 'Editar usuario' : 'Nuevo usuario' }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Nombre *</label>
                    <input type="text" wire:model="name" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('name') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Email *</label>
                    <input type="email" wire:model="email" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('email') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Teléfono</label>
                    <input type="text" wire:model="telefono" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Estado</label>
                    <x-select wire:model="estado" :placeholder="null" :reset-key="'estado-'.($editandoId ?? 'nuevo')">
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </x-select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
                        {{ $editandoId ? 'Nueva contraseña (opcional)' : 'Contraseña *' }}
                    </label>
                    <input type="password" wire:model="password" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('password') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Roles</label>
                    <div class="flex gap-4 flex-wrap">
                        @foreach ($todosLosRoles as $rol)
                            <label class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                                <input type="checkbox" wire:model.live="roles" value="{{ $rol }}" class="accent-brand-blue">
                                {{ $rol }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($this->esTecnico())
                <div class="mt-5 pt-5 border-t border-slate-100 dark:border-slate-800">
                    <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-3">Ficha de técnico</div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Especialidad *</label>
                            <x-select wire:model="especialidadId" :reset-key="'especialidad-'.($editandoId ?? 'nuevo')">
                                @foreach ($especialidades as $especialidad)
                                    <option value="{{ $especialidad->id }}">{{ $especialidad->nombre }}</option>
                                @endforeach
                            </x-select>
                            @error('especialidadId') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Tarifa por hora</label>
                            <input type="number" step="0.01" min="0" wire:model="tarifaHora" placeholder="Opcional"
                                   class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                            @error('tarifaHora') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex items-end pb-2.5">
                            <label class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                                <input type="checkbox" wire:model="tecnicoActivo" class="accent-brand-blue">
                                Disponible para asignación de tareas
                            </label>
                        </div>
                    </div>
                </div>
            @endif

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
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Nombre</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Email</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Roles</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Especialidad</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Estado</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($usuarios as $u)
                        <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-5 py-3 font-medium whitespace-nowrap">{{ $u->name }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $u->email }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $u->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $u->tecnico?->especialidad?->nombre ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-block px-2.5 py-1 rounded-full text-[11.5px] font-semibold whitespace-nowrap {{ $u->estado === 'activo' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">
                                    {{ ucfirst($u->estado) }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <x-icon-button wire:click="editar({{ $u->id }})" title="Editar usuario">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 17h4l10-10-4-4L4 13v4z"/></svg>
                                    </x-icon-button>
                                    @if ($u->estado === 'activo')
                                        <x-icon-button
                                            x-on:click="Notify.confirmDanger({
                                                title: '¿Inactivar usuario?',
                                                text: {{ Js::from($u->name.' perderá acceso al sistema hasta que sea reactivado.') }},
                                                confirmButtonText: 'Sí, inactivar',
                                            }).then((ok) => ok && $wire.alternarEstado({{ $u->id }}))"
                                            title="Inactivar usuario" variant="danger">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M6 6l12 12"/></svg>
                                        </x-icon-button>
                                    @else
                                        <x-icon-button wire:click="alternarEstado({{ $u->id }})" title="Activar usuario" variant="success">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
                                        </x-icon-button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">Sin usuarios registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3.5">{{ $usuarios->links() }}</div>
    </div>
</div>
