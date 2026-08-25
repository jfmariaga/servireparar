<?php

use App\Livewire\Concerns\Notifies;
use App\Models\CategoriaInventario;
use App\Models\Inventario;
use App\Models\UnidadMedida;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Categorías y unidades'])] class extends Component
{
    use Notifies;

    public const PREFIJOS = ['REP-' => 'REP- (Repuestos)', 'HER-' => 'HER- (Herramientas)', 'CON-' => 'CON- (Consumibles)', 'ACC-' => 'ACC- (Accesorios)'];

    public bool $mostrarFormCategoria = false;
    public ?int $editandoCategoriaId = null;
    public string $categoriaNombre = '';
    public string $categoriaPrefijo = 'REP-';

    public bool $mostrarFormUnidad = false;
    public ?int $editandoUnidadId = null;
    public string $unidadNombre = '';
    public string $unidadAbreviatura = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Inventario::class);
    }

    public function with(): array
    {
        return [
            'categorias' => CategoriaInventario::orderBy('nombre')->get(),
            'unidades' => UnidadMedida::orderBy('nombre')->get(),
        ];
    }

    public function nuevaCategoria(): void
    {
        Gate::authorize('create', Inventario::class);
        $this->reset(['editandoCategoriaId', 'categoriaNombre']);
        $this->categoriaPrefijo = 'REP-';
        $this->mostrarFormCategoria = true;
    }

    public function editarCategoria(int $id): void
    {
        Gate::authorize('update', Inventario::class);
        $categoria = CategoriaInventario::findOrFail($id);
        $this->editandoCategoriaId = $categoria->id;
        $this->categoriaNombre = $categoria->nombre;
        $this->categoriaPrefijo = $categoria->prefijo_codigo;
        $this->mostrarFormCategoria = true;
    }

    public function guardarCategoria(): void
    {
        Gate::authorize($this->editandoCategoriaId ? 'update' : 'create', Inventario::class);

        $datos = $this->validate([
            'categoriaNombre' => 'required|string|max:100|unique:categorias_inventario,nombre,'.$this->editandoCategoriaId,
            'categoriaPrefijo' => 'required|in:'.implode(',', array_keys(self::PREFIJOS)),
        ], [], ['categoriaNombre' => 'nombre', 'categoriaPrefijo' => 'prefijo']);

        CategoriaInventario::updateOrCreate(['id' => $this->editandoCategoriaId], [
            'nombre' => $datos['categoriaNombre'],
            'prefijo_codigo' => $datos['categoriaPrefijo'],
            'activo' => true,
        ]);

        $esNueva = ! $this->editandoCategoriaId;
        $this->mostrarFormCategoria = false;
        $this->notifySuccess($esNueva ? 'Categoría creada correctamente.' : 'Categoría actualizada correctamente.');
    }

    public function cancelarCategoria(): void
    {
        $this->mostrarFormCategoria = false;
    }

    public function alternarCategoria(int $id): void
    {
        Gate::authorize('update', Inventario::class);
        $categoria = CategoriaInventario::findOrFail($id);
        $categoria->update(['activo' => ! $categoria->activo]);
        $this->notifySuccess($categoria->activo ? 'Categoría activada.' : 'Categoría inactivada.');
    }

    public function nuevaUnidad(): void
    {
        Gate::authorize('create', Inventario::class);
        $this->reset(['editandoUnidadId', 'unidadNombre', 'unidadAbreviatura']);
        $this->mostrarFormUnidad = true;
    }

    public function editarUnidad(int $id): void
    {
        Gate::authorize('update', Inventario::class);
        $unidad = UnidadMedida::findOrFail($id);
        $this->editandoUnidadId = $unidad->id;
        $this->unidadNombre = $unidad->nombre;
        $this->unidadAbreviatura = (string) $unidad->abreviatura;
        $this->mostrarFormUnidad = true;
    }

    public function guardarUnidad(): void
    {
        Gate::authorize($this->editandoUnidadId ? 'update' : 'create', Inventario::class);

        $datos = $this->validate([
            'unidadNombre' => 'required|string|max:50|unique:unidades_medida,nombre,'.$this->editandoUnidadId,
            'unidadAbreviatura' => 'nullable|string|max:10',
        ], [], ['unidadNombre' => 'nombre', 'unidadAbreviatura' => 'abreviatura']);

        UnidadMedida::updateOrCreate(['id' => $this->editandoUnidadId], [
            'nombre' => $datos['unidadNombre'],
            'abreviatura' => $datos['unidadAbreviatura'] ?: null,
            'activo' => true,
        ]);

        $esNueva = ! $this->editandoUnidadId;
        $this->mostrarFormUnidad = false;
        $this->notifySuccess($esNueva ? 'Unidad creada correctamente.' : 'Unidad actualizada correctamente.');
    }

    public function cancelarUnidad(): void
    {
        $this->mostrarFormUnidad = false;
    }

    public function alternarUnidad(int $id): void
    {
        Gate::authorize('update', Inventario::class);
        $unidad = UnidadMedida::findOrFail($id);
        $unidad->update(['activo' => ! $unidad->activo]);
        $this->notifySuccess($unidad->activo ? 'Unidad activada.' : 'Unidad inactivada.');
    }
}; ?>

<div>
    @include('partials.inventario-tabs')

    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6 max-w-2xl">
        Estos catálogos alimentan los selectores del formulario de ítems. Gestiónalos aquí para evitar
        nombres distintos para lo mismo por error de digitación — al inactivar una categoría o unidad, los
        ítems que ya la usan no se ven afectados, solo deja de ofrecerse para ítems nuevos.
    </p>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-sm">Categorías</h2>
                <x-icon-button wire:click="nuevaCategoria" title="Nueva categoría" variant="primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
                </x-icon-button>
            </div>

            @if ($mostrarFormCategoria)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Nombre *</label>
                            <input type="text" wire:model="categoriaNombre" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                            @error('categoriaNombre') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Prefijo *</label>
                            <x-select wire:model="categoriaPrefijo" :placeholder="null" :reset-key="'prefijo-'.($editandoCategoriaId ?? 'nuevo')">
                                @foreach (self::PREFIJOS as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                            </x-select>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <button wire:click="guardarCategoria" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13px] font-semibold px-3.5 py-2 rounded-lg transition">Guardar</button>
                        <button wire:click="cancelarCategoria" class="text-[13px] font-semibold px-3.5 py-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Cancelar</button>
                    </div>
                </div>
            @endif

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                            <th class="px-4 py-2.5 text-[11px] font-bold uppercase tracking-wide text-slate-400">Nombre</th>
                            <th class="px-4 py-2.5 text-[11px] font-bold uppercase tracking-wide text-slate-400">Prefijo</th>
                            <th class="px-4 py-2.5 text-[11px] font-bold uppercase tracking-wide text-slate-400">Estado</th>
                            <th class="px-4 py-2.5 text-[11px] font-bold uppercase tracking-wide text-slate-400"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categorias as $categoria)
                            <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                                <td class="px-4 py-2.5 font-medium">{{ $categoria->nombre }}</td>
                                <td class="px-4 py-2.5 font-mono text-[12px] text-slate-500 dark:text-slate-400">{{ $categoria->prefijo_codigo }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $categoria->activo ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">
                                        {{ $categoria->activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-1.5 justify-end">
                                        <x-icon-button wire:click="editarCategoria({{ $categoria->id }})" title="Editar categoría">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 17h4l10-10-4-4L4 13v4z"/></svg>
                                        </x-icon-button>
                                        <x-icon-button wire:click="alternarCategoria({{ $categoria->id }})" title="{{ $categoria->activo ? 'Inactivar' : 'Activar' }} categoría" variant="{{ $categoria->activo ? 'danger' : 'success' }}">
                                            @if ($categoria->activo)
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M6 6l12 12"/></svg>
                                            @else
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
                                            @endif
                                        </x-icon-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Sin categorías registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-sm">Unidades de medida</h2>
                <x-icon-button wire:click="nuevaUnidad" title="Nueva unidad" variant="primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
                </x-icon-button>
            </div>

            @if ($mostrarFormUnidad)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Nombre *</label>
                            <input type="text" wire:model="unidadNombre" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                            @error('unidadNombre') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Abreviatura</label>
                            <input type="text" wire:model="unidadAbreviatura" placeholder="Ej. gal, kg" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                            @error('unidadAbreviatura') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <button wire:click="guardarUnidad" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13px] font-semibold px-3.5 py-2 rounded-lg transition">Guardar</button>
                        <button wire:click="cancelarUnidad" class="text-[13px] font-semibold px-3.5 py-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Cancelar</button>
                    </div>
                </div>
            @endif

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                            <th class="px-4 py-2.5 text-[11px] font-bold uppercase tracking-wide text-slate-400">Nombre</th>
                            <th class="px-4 py-2.5 text-[11px] font-bold uppercase tracking-wide text-slate-400">Abrev.</th>
                            <th class="px-4 py-2.5 text-[11px] font-bold uppercase tracking-wide text-slate-400">Estado</th>
                            <th class="px-4 py-2.5 text-[11px] font-bold uppercase tracking-wide text-slate-400"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($unidades as $unidad)
                            <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                                <td class="px-4 py-2.5 font-medium">{{ $unidad->nombre }}</td>
                                <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ $unidad->abreviatura ?: '—' }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $unidad->activo ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">
                                        {{ $unidad->activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-1.5 justify-end">
                                        <x-icon-button wire:click="editarUnidad({{ $unidad->id }})" title="Editar unidad">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 17h4l10-10-4-4L4 13v4z"/></svg>
                                        </x-icon-button>
                                        <x-icon-button wire:click="alternarUnidad({{ $unidad->id }})" title="{{ $unidad->activo ? 'Inactivar' : 'Activar' }} unidad" variant="{{ $unidad->activo ? 'danger' : 'success' }}">
                                            @if ($unidad->activo)
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M6 6l12 12"/></svg>
                                            @else
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
                                            @endif
                                        </x-icon-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Sin unidades registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
