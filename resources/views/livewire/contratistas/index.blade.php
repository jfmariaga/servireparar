<?php

use App\Livewire\Concerns\Notifies;
use App\Models\Contratista;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layout', ['title' => 'Contratistas'])] class extends Component
{
    use Notifies, WithPagination;

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

        $esNuevo = ! $this->editandoId;

        Contratista::updateOrCreate(['id' => $this->editandoId], $datos);
        $this->mostrarForm = false;
        $this->notifySuccess($esNuevo ? 'Contratista creado correctamente.' : 'Contratista actualizado correctamente.');
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
        $this->notifySuccess($c->estado === 'activo' ? 'Contratista activado.' : 'Contratista inactivado.');
    }
}; ?>

<div>
    <div class="flex items-center justify-end mb-6">
        <x-icon-button wire:click="nuevo" title="Nuevo contratista" variant="primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
        </x-icon-button>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 mb-5 text-sm">
        <div class="relative flex-1 sm:flex-none">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" wire:model.live.debounce.400ms="busqueda" placeholder="Buscar por nombre..."
                   class="border border-slate-200 dark:border-slate-700 dark:bg-slate-900 rounded-lg pl-9 pr-3 py-2 w-full sm:w-64 outline-none focus:border-brand-blue">
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
            <h2 class="font-bold mb-4">{{ $editandoId ? 'Editar contratista' : 'Nuevo contratista' }}</h2>

            @if ($advertenciaDuplicado)
                <div class="mb-4 rounded-lg bg-amber-50 text-amber-800 text-sm px-3.5 py-2.5">
                    ⚠ {{ $advertenciaDuplicado }} Puedes continuar si confirmas que es un registro distinto.
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div class="sm:col-span-2">
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Nombre / Empresa *</label>
                    <input type="text" wire:model="nombre" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('nombre') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Especialidad</label>
                    <input type="text" wire:model="especialidad" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue" placeholder="Ej. Calcomanías, soldadura...">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Teléfono</label>
                    <input type="text" wire:model="telefono" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Correo</label>
                    <input type="email" wire:model.blur="correo" wire:blur="verificarDuplicado" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('correo') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Estado</label>
                    <x-select wire:model="estado" :placeholder="null" :reset-key="'estado-'.($editandoId ?? 'nuevo')">
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </x-select>
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
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Nombre</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Especialidad</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Correo</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Estado</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contratistas as $c)
                        <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-5 py-3 font-medium whitespace-nowrap">{{ $c->nombre }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $c->especialidad ?: '—' }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $c->correo ?: '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-block px-2.5 py-1 rounded-full text-[11.5px] font-semibold whitespace-nowrap {{ $c->estado === 'activo' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">
                                    {{ ucfirst($c->estado) }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <x-icon-button wire:click="editar({{ $c->id }})" title="Editar contratista">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 17h4l10-10-4-4L4 13v4z"/></svg>
                                    </x-icon-button>
                                    @if ($c->estado === 'activo')
                                        <x-icon-button
                                            x-on:click="Notify.confirmDanger({
                                                title: '¿Inactivar contratista?',
                                                text: {{ Js::from($c->nombre.' dejará de estar disponible para nuevas operaciones.') }},
                                                confirmButtonText: 'Sí, inactivar',
                                            }).then((ok) => ok && $wire.alternarEstado({{ $c->id }}))"
                                            title="Inactivar contratista" variant="danger">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M6 6l12 12"/></svg>
                                        </x-icon-button>
                                    @else
                                        <x-icon-button wire:click="alternarEstado({{ $c->id }})" title="Activar contratista" variant="success">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
                                        </x-icon-button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">Sin contratistas registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3.5">{{ $contratistas->links() }}</div>
    </div>
</div>
