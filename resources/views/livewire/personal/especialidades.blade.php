<?php

use App\Livewire\Concerns\Notifies;
use App\Models\Especialidad;
use App\Models\Tecnico;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Especialidades'])] class extends Component
{
    use Notifies;

    public bool $mostrarForm = false;
    public ?int $editandoId = null;
    public string $nombre = '';

    public function mount(): void
    {
        Gate::authorize('manage', Tecnico::class);
    }

    public function with(): array
    {
        return [
            'especialidades' => Especialidad::withCount('tecnicos')->orderBy('nombre')->get(),
        ];
    }

    public function nueva(): void
    {
        Gate::authorize('manage', Tecnico::class);
        $this->reset(['editandoId', 'nombre']);
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        Gate::authorize('manage', Tecnico::class);
        $especialidad = Especialidad::findOrFail($id);
        $this->editandoId = $especialidad->id;
        $this->nombre = $especialidad->nombre;
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        Gate::authorize('manage', Tecnico::class);

        $datos = $this->validate([
            'nombre' => 'required|string|max:100|unique:especialidades,nombre,'.$this->editandoId,
        ]);

        Especialidad::updateOrCreate(['id' => $this->editandoId], [
            'nombre' => $datos['nombre'],
            'activo' => true,
        ]);

        $esNueva = ! $this->editandoId;
        $this->mostrarForm = false;
        $this->notifySuccess($esNueva ? 'Especialidad creada correctamente.' : 'Especialidad actualizada correctamente.');
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
    }

    public function alternar(int $id): void
    {
        Gate::authorize('manage', Tecnico::class);
        $especialidad = Especialidad::findOrFail($id);
        $especialidad->update(['activo' => ! $especialidad->activo]);
        $this->notifySuccess($especialidad->activo ? 'Especialidad activada.' : 'Especialidad inactivada.');
    }
}; ?>

<div>
    @include('partials.usuarios-tabs')

    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6 max-w-2xl">
        Catálogo fijo que alimenta el selector de especialidad al asignar el rol Técnico desde Usuarios.
        Inactivar una especialidad no afecta a los técnicos que ya la tienen asignada, solo deja de
        ofrecerse para nuevas asignaciones.
    </p>

    <div class="flex items-center justify-end mb-6">
        <x-icon-button wire:click="nueva" title="Nueva especialidad" variant="primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
        </x-icon-button>
    </div>

    @if ($mostrarForm)
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 mb-6">
            <h2 class="font-bold mb-4">{{ $editandoId ? 'Editar especialidad' : 'Nueva especialidad' }}</h2>
            <div class="max-w-sm text-sm">
                <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Nombre *</label>
                <input type="text" wire:model="nombre" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                @error('nombre') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
            </div>
            <div class="flex gap-2 mt-6">
                <button wire:click="guardar" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">Guardar</button>
                <button wire:click="cancelar" class="text-[13.5px] font-semibold px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Cancelar</button>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden max-w-2xl">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Nombre</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Técnicos</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Estado</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($especialidades as $especialidad)
                    <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-5 py-3 font-medium">{{ $especialidad->nombre }}</td>
                        <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $especialidad->tecnicos_count }}</td>
                        <td class="px-5 py-3">
                            <span class="inline-block px-2.5 py-1 rounded-full text-[11.5px] font-semibold {{ $especialidad->activo ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">
                                {{ $especialidad->activo ? 'Activa' : 'Inactiva' }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2 justify-end">
                                <x-icon-button wire:click="editar({{ $especialidad->id }})" title="Editar especialidad">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 17h4l10-10-4-4L4 13v4z"/></svg>
                                </x-icon-button>
                                <x-icon-button wire:click="alternar({{ $especialidad->id }})" title="{{ $especialidad->activo ? 'Inactivar' : 'Activar' }} especialidad" variant="{{ $especialidad->activo ? 'danger' : 'success' }}">
                                    @if ($especialidad->activo)
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M6 6l12 12"/></svg>
                                    @else
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
                                    @endif
                                </x-icon-button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-slate-400">Sin especialidades registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
