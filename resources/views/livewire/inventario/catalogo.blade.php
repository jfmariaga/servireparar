<?php

use App\Livewire\Concerns\Notifies;
use App\Models\CategoriaInventario;
use App\Models\Inventario;
use App\Models\UnidadMedida;
use App\Services\Inventario\CodigoInternoService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layout', ['title' => 'Inventario'])] class extends Component
{
    use Notifies, WithPagination;

    public string $filtroCategoria = 'todas';
    public string $filtroTipo = 'todos';
    public string $busqueda = '';

    public bool $mostrarForm = false;
    public ?int $editandoId = null;

    public ?int $categoriaId = null;
    public string $nombre = '';
    public string $tipo = 'consumible';
    public string $ubicacion = '';
    public ?int $unidadMedidaId = null;
    public string $stockMinimo = '0';

    public string $advertenciaUbicacion = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Inventario::class);
    }

    public function with(): array
    {
        return [
            'items' => Inventario::query()
                ->activos()
                ->when($this->filtroCategoria !== 'todas', fn ($q) => $q->where('categoria_id', $this->filtroCategoria))
                ->when($this->filtroTipo !== 'todos', fn ($q) => $q->where('tipo', $this->filtroTipo))
                ->when($this->busqueda, fn ($q) => $q->where(fn ($q2) => $q2
                    ->where('nombre', 'like', "%{$this->busqueda}%")
                    ->orWhere('codigo', 'like', "%{$this->busqueda}%")))
                ->with(['categoria', 'unidadMedida', 'lotesDisponibles'])
                ->orderByDesc('id')
                ->paginate(10),
            'categorias' => CategoriaInventario::activas()->orderBy('nombre')->get(),
            'unidadesMedida' => UnidadMedida::activas()->orderBy('nombre')->get(),
        ];
    }

    public function nuevo(): void
    {
        Gate::authorize('create', Inventario::class);
        $this->reset(['categoriaId', 'nombre', 'ubicacion', 'unidadMedidaId', 'editandoId', 'advertenciaUbicacion']);
        $this->tipo = 'consumible';
        $this->stockMinimo = '0';
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        Gate::authorize('update', Inventario::class);
        $item = Inventario::findOrFail($id);
        $this->editandoId = $item->id;
        $this->categoriaId = $item->categoria_id;
        $this->nombre = $item->nombre;
        $this->tipo = $item->tipo;
        $this->ubicacion = (string) $item->ubicacion;
        $this->unidadMedidaId = $item->unidad_medida_id;
        $this->stockMinimo = (string) $item->stock_minimo;
        $this->advertenciaUbicacion = '';
        $this->mostrarForm = true;
    }

    public function verificarUbicacion(): void
    {
        $this->advertenciaUbicacion = '';

        if (! $this->ubicacion) {
            return;
        }

        $colision = Inventario::where('ubicacion', $this->ubicacion)
            ->when($this->editandoId, fn ($q) => $q->where('id', '!=', $this->editandoId))
            ->exists();

        if ($colision) {
            $this->advertenciaUbicacion = "Ya hay otro ítem registrado en la ubicación {$this->ubicacion}.";
        }
    }

    public function guardar(): void
    {
        Gate::authorize($this->editandoId ? 'update' : 'create', Inventario::class);

        $datos = $this->validate([
            'categoriaId' => 'required|exists:categorias_inventario,id',
            'nombre' => 'required|string|max:150',
            'tipo' => 'required|in:herramienta,consumible',
            'ubicacion' => ['nullable', 'regex:/^[A-Za-z]-\d{2}-\d{2}$/'],
            'unidadMedidaId' => 'nullable|exists:unidades_medida,id',
            'stockMinimo' => 'required|numeric|min:0',
        ], [
            'ubicacion.regex' => 'El formato debe ser PASILLO-ESTANTE-NIVEL, ej. A-01-01.',
        ]);

        $this->verificarUbicacion();

        if ($this->editandoId) {
            $item = Inventario::findOrFail($this->editandoId);
            $item->update([
                'categoria_id' => $datos['categoriaId'],
                'nombre' => $datos['nombre'],
                'tipo' => $datos['tipo'],
                'ubicacion' => $datos['ubicacion'] ?: null,
                'unidad_medida_id' => $datos['unidadMedidaId'],
                'stock_minimo' => $datos['stockMinimo'],
            ]);
        } else {
            $categoria = CategoriaInventario::findOrFail($datos['categoriaId']);
            $codigo = (new CodigoInternoService())->generar($categoria);

            Inventario::create([
                'codigo' => $codigo,
                'nombre' => $datos['nombre'],
                'tipo' => $datos['tipo'],
                'categoria_id' => $categoria->id,
                'ubicacion' => $datos['ubicacion'] ?: null,
                'codigo_barras' => $codigo,
                'unidad_medida_id' => $datos['unidadMedidaId'],
                'stock_actual' => 0,
                'stock_minimo' => $datos['stockMinimo'],
                'estado_herramienta' => $datos['tipo'] === 'herramienta' ? 'disponible' : null,
                'activo' => true,
            ]);
        }

        $esNuevo = ! $this->editandoId;
        $this->mostrarForm = false;
        $this->notifySuccess($esNuevo ? 'Ítem registrado correctamente.' : 'Ítem actualizado correctamente.');
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
    }
}; ?>

<div>
    @include('partials.inventario-tabs')

    <div class="flex items-center justify-end mb-6">
        <x-icon-button wire:click="nuevo" title="Nuevo ítem" variant="primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
        </x-icon-button>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 mb-5 text-sm">
        <div class="relative flex-1 sm:flex-none">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" wire:model.live.debounce.400ms="busqueda" placeholder="Buscar por nombre o código..."
                   class="border border-slate-200 dark:border-slate-700 dark:bg-slate-900 rounded-lg pl-9 pr-3 py-2 w-full sm:w-72 outline-none focus:border-brand-blue">
        </div>
        <div class="w-full sm:w-56">
            <x-select wire:model.live="filtroCategoria" :placeholder="null">
                <option value="todas">Todas las categorías</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
                @endforeach
            </x-select>
        </div>
        <div class="w-full sm:w-56">
            <x-select wire:model.live="filtroTipo" :placeholder="null">
                <option value="todos">Herramientas y consumibles</option>
                <option value="herramienta">Herramientas</option>
                <option value="consumible">Consumibles</option>
            </x-select>
        </div>
    </div>

    @if ($mostrarForm)
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 mb-6">
            <h2 class="font-bold mb-4">{{ $editandoId ? 'Editar ítem' : 'Nuevo ítem' }}</h2>

            @if ($advertenciaUbicacion)
                <div class="mb-4 rounded-lg bg-amber-50 text-amber-800 text-sm px-3.5 py-2.5">⚠ {{ $advertenciaUbicacion }} Puedes continuar si confirmas que es correcto.</div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Categoría *</label>
                    <x-select wire:model="categoriaId" :reset-key="'categoria-'.($editandoId ?? 'nuevo')">
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria->id }}">{{ $categoria->nombre }} ({{ $categoria->prefijo_codigo }})</option>
                        @endforeach
                    </x-select>
                    @error('categoriaId') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Nombre *</label>
                    <input type="text" wire:model="nombre" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('nombre') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Tipo *</label>
                    <x-select wire:model="tipo" :placeholder="null" :disabled="(bool) $editandoId" :reset-key="'tipo-'.($editandoId ?? 'nuevo')">
                        <option value="consumible">Consumible</option>
                        <option value="herramienta">Herramienta</option>
                    </x-select>
                    @if ($editandoId)
                        <p class="text-xs text-slate-400 mt-1">El tipo no se puede cambiar una vez el ítem tiene movimientos.</p>
                    @endif
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Ubicación</label>
                    <input type="text" wire:model.blur="ubicacion" wire:blur="verificarUbicacion" placeholder="A-01-01" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('ubicacion') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Unidad de medida</label>
                    <x-select wire:model="unidadMedidaId" :reset-key="'unidad-'.($editandoId ?? 'nuevo')">
                        @foreach ($unidadesMedida as $unidad)
                            <option value="{{ $unidad->id }}">{{ $unidad->nombre }}{{ $unidad->abreviatura ? " ({$unidad->abreviatura})" : '' }}</option>
                        @endforeach
                    </x-select>
                    @error('unidadMedidaId') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Stock mínimo</label>
                    <input type="number" step="0.01" min="0" wire:model="stockMinimo" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
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
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Código</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Nombre</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Categoría</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Ubicación</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Stock</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Valor total</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-5 py-3 font-mono text-[12.5px] font-semibold whitespace-nowrap">{{ $item->codigo }}</td>
                            <td class="px-5 py-3 font-medium whitespace-nowrap">{{ $item->nombre }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $item->categoria->nombre }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $item->ubicacion ?: '—' }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                @if ($item->tipo === 'herramienta')
                                    <span class="inline-block px-2.5 py-1 rounded-full text-[11.5px] font-semibold {{ $item->estado_herramienta === 'disponible' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">
                                        {{ ucfirst(str_replace('_', ' ', $item->estado_herramienta)) }}
                                    </span>
                                @else
                                    <span @class([
                                        'font-semibold',
                                        'text-brand-red' => $item->stockBajoMinimo(),
                                    ])>{{ rtrim(rtrim(number_format((float) $item->stock_actual, 2), '0'), '.') }} {{ $item->unidadMedida?->abreviatura }}</span>
                                    @if ($item->stockBajoMinimo())
                                        <span class="ml-1 text-[11px] text-brand-red font-semibold">bajo mínimo</span>
                                    @endif
                                @endif
                            </td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $item->valorTotalFormateado() }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <x-icon-button wire:click="editar({{ $item->id }})" title="Editar ítem">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 17h4l10-10-4-4L4 13v4z"/></svg>
                                    </x-icon-button>
                                    <a href="{{ route('inventario.etiqueta', $item) }}" target="_blank" title="Ver/imprimir etiqueta"
                                       class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="6" width="18" height="12" rx="1"/><path d="M7 6v12M11 6v12M15 6v12"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-slate-400">Sin ítems registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3.5">{{ $items->links() }}</div>
    </div>
</div>
