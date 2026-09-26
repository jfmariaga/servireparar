<?php

use App\Livewire\Concerns\Notifies;
use App\Models\Servicio;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Servicios'])] class extends Component
{
    use Notifies;

    public bool $mostrarForm = false;
    public ?int $editandoId = null;
    public string $nombre = '';
    public string $costoUnitario = '';
    public string $unidadMedida = '';

    public function mount(): void
    {
        Gate::authorize('manage', \App\Models\Cotizacion::class);
    }

    public function with(): array
    {
        return [
            'servicios' => Servicio::orderBy('nombre')->get(),
        ];
    }

    public function nueva(): void
    {
        $this->reset(['editandoId', 'nombre', 'costoUnitario', 'unidadMedida']);
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $servicio = Servicio::findOrFail($id);
        $this->editandoId = $servicio->id;
        $this->nombre = $servicio->nombre;
        $this->costoUnitario = (string) $servicio->costo_unitario;
        $this->unidadMedida = (string) $servicio->unidad_medida;
        $this->mostrarForm = true;
    }

    public function guardar(): void
    {
        $datos = $this->validate([
            'nombre' => 'required|string|max:150',
            'costoUnitario' => 'required|numeric|min:0',
            'unidadMedida' => 'nullable|string|max:20',
        ]);

        Servicio::updateOrCreate(['id' => $this->editandoId], [
            'nombre' => $datos['nombre'],
            'costo_unitario' => $datos['costoUnitario'],
            'unidad_medida' => $datos['unidadMedida'] ?: null,
            'activo' => true,
        ]);

        $esNuevo = ! $this->editandoId;
        $this->mostrarForm = false;
        $this->notifySuccess($esNuevo ? 'Servicio creado correctamente.' : 'Servicio actualizado correctamente.');
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
    }

    public function alternar(int $id): void
    {
        $servicio = Servicio::findOrFail($id);
        $servicio->update(['activo' => ! $servicio->activo]);
        $this->notifySuccess($servicio->activo ? 'Servicio activado.' : 'Servicio inactivado.');
    }
}; ?>

<div>
    <x-breadcrumbs :items="[['label' => 'Cotizaciones', 'route' => 'cotizaciones.tablero'], ['label' => 'Servicios']]" />

    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6 max-w-2xl">
        Maestra de servicios cotizables, complementa el catálogo de insumos de Inventario al construir una
        Cotización.
    </p>

    <div class="flex items-center justify-end mb-6">
        <x-icon-button wire:click="nueva" title="Nuevo servicio" variant="primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
        </x-icon-button>
    </div>

    @if ($mostrarForm)
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 mb-6">
            <h2 class="font-bold mb-4">{{ $editandoId ? 'Editar servicio' : 'Nuevo servicio' }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-2xl text-sm">
                <div class="sm:col-span-2">
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Nombre *</label>
                    <input type="text" wire:model="nombre" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('nombre') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Costo unitario *</label>
                    <input type="number" step="0.01" min="0" wire:model="costoUnitario" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('costoUnitario') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-3">
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Unidad de medida</label>
                    <input type="text" wire:model="unidadMedida" placeholder="ej. hora, servicio, visita" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                </div>
            </div>
            <div class="flex gap-2 mt-6">
                <button wire:click="guardar" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">Guardar</button>
                <button wire:click="cancelar" class="text-[13.5px] font-semibold px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Cancelar</button>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden max-w-3xl">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Nombre</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Costo unitario</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Estado</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($servicios as $servicio)
                    <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-5 py-3 font-medium">{{ $servicio->nombre }}</td>
                        <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ \App\Support\Moneda::cop($servicio->costo_unitario) }}{{ $servicio->unidad_medida ? ' / '.$servicio->unidad_medida : '' }}</td>
                        <td class="px-5 py-3">
                            <span class="inline-block px-2.5 py-1 rounded-full text-[11.5px] font-semibold {{ $servicio->activo ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">
                                {{ $servicio->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2 justify-end">
                                <x-icon-button wire:click="editar({{ $servicio->id }})" title="Editar servicio">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 17h4l10-10-4-4L4 13v4z"/></svg>
                                </x-icon-button>
                                <x-icon-button wire:click="alternar({{ $servicio->id }})" title="{{ $servicio->activo ? 'Inactivar' : 'Activar' }} servicio" variant="{{ $servicio->activo ? 'danger' : 'success' }}">
                                    @if ($servicio->activo)
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M6 6l12 12"/></svg>
                                    @else
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
                                    @endif
                                </x-icon-button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-slate-400">Sin servicios registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
