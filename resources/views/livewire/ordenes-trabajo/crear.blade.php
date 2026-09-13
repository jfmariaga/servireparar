<?php

use App\Enums\RolPrioridad;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use App\Models\Prioridad;
use App\Models\SolicitudInsumoOt;
use App\Models\Tecnico;
use App\Services\OrdenTrabajo\OrdenTrabajoService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layout', ['title' => 'Nueva orden de trabajo'])] class extends Component
{
    use WithFileUploads;

    public ?int $clienteId = null;
    public ?int $prioridadId = null;
    public string $tipoServicio = 'taller';
    public string $direccionServicio = '';
    public string $descripcion = '';
    public string $tiempoEstimadoDias = '';
    public string $valorProyecto = '';

    public string $equipoDescripcion = '';
    public string $equipoMarca = '';
    public string $equipoModelo = '';
    public string $equipoSerie = '';
    public string $equipoEstadoIngreso = '';

    public $fotoEntrada = null;

    /** @var array<int, array<string, mixed>> */
    public array $tareas = [];

    public function mount(): void
    {
        Gate::authorize('create', OrdenTrabajo::class);
        $this->prioridadId = Prioridad::where('nombre', 'Media')->value('id');
        $this->agregarTarea();
    }

    public function with(): array
    {
        return [
            'clientes' => Cliente::activos()->orderBy('nombre')->get(['id', 'nombre']),
            'prioridades' => Prioridad::orderBy('nivel')->get(['id', 'nombre']),
            'tecnicos' => Tecnico::disponibles()->with('usuario:id,name')->get()
                ->map(fn (Tecnico $t) => ['id' => $t->id, 'nombre' => $t->usuario?->name ?? 'Técnico #'.$t->id]),
            'insumos' => $this->insumosDisponibles(),
            // El valor del proyecto lo define el Administrador (ability manageCosteo, normalmente
            // desde la pantalla de Costeo); el Jefe de Taller no lo ve al crear la OT.
            'puedeDefinirValor' => auth()->user()->hasRole(RolPrioridad::Administrador->value),
        ];
    }

    /**
     * Consumibles activos con su disponible real (stock − comprometido en solicitudes
     * de insumo de OT sin despachar). Phase 11 / D1.
     *
     * @return \Illuminate\Support\Collection<int, array{id:int, nombre:string, codigo:string, disponible:float}>
     */
    private function insumosDisponibles(): \Illuminate\Support\Collection
    {
        $comprometido = SolicitudInsumoOt::comprometidas()
            ->selectRaw('inventario_id, SUM(cantidad) total')
            ->groupBy('inventario_id')
            ->pluck('total', 'inventario_id');

        return Inventario::activos()->where('tipo', 'consumible')->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo', 'stock_actual'])
            ->map(fn (Inventario $i) => [
                'id' => $i->id,
                'nombre' => $i->nombre,
                'codigo' => $i->codigo,
                'disponible' => (float) $i->stock_actual - (float) ($comprometido[$i->id] ?? 0),
            ]);
    }

    public function agregarTarea(): void
    {
        $this->tareas[] = [
            'uid' => (string) Str::uuid(),
            'descripcion' => '',
            'tecnico_id' => null,
            'dias_cumplimiento' => '',
            'insumos' => [],
            'prerrequisitos' => [],
        ];
    }

    public function quitarTarea(int $i): void
    {
        $uid = $this->tareas[$i]['uid'] ?? null;
        unset($this->tareas[$i]);
        $this->tareas = array_values($this->tareas);
        if ($this->tareas === []) {
            $this->agregarTarea();
        }
        // Limpia referencias colgantes al uid eliminado.
        if ($uid !== null) {
            foreach ($this->tareas as &$t) {
                $t['prerrequisitos'] = array_values(array_filter($t['prerrequisitos'] ?? [], fn ($u) => $u !== $uid));
            }
        }
    }

    public function agregarInsumo(int $i): void
    {
        $this->tareas[$i]['insumos'][] = ['inventario_id' => null, 'cantidad' => ''];
    }

    public function quitarInsumo(int $i, int $li): void
    {
        unset($this->tareas[$i]['insumos'][$li]);
        $this->tareas[$i]['insumos'] = array_values($this->tareas[$i]['insumos']);
    }

    public function guardar(OrdenTrabajoService $servicio): void
    {
        Gate::authorize('create', OrdenTrabajo::class);

        $datos = $this->validate([
            'clienteId' => 'required|exists:clientes,id',
            'prioridadId' => 'required|exists:prioridades,id',
            'tipoServicio' => 'required|in:taller,domicilio',
            'direccionServicio' => 'nullable|string|max:255|required_if:tipoServicio,domicilio',
            'descripcion' => 'required|string|max:2000',
            'tiempoEstimadoDias' => 'nullable|numeric|min:0',
            'valorProyecto' => 'nullable|numeric|min:0',
            'equipoMarca' => 'nullable|string|max:100',
            'equipoModelo' => 'nullable|string|max:100',
            'equipoSerie' => 'nullable|string|max:100',
            'equipoEstadoIngreso' => 'nullable|string|max:255',
            'fotoEntrada' => 'nullable|image|max:5120',
            'tareas' => 'required|array|min:1',
            'tareas.*.descripcion' => 'required|string|max:1000',
            'tareas.*.tecnico_id' => 'required|exists:tecnicos,id',
            'tareas.*.dias_cumplimiento' => 'nullable|numeric|min:0.5',
            'tareas.*.insumos' => 'array',
            'tareas.*.insumos.*.inventario_id' => 'required|exists:inventario,id',
            'tareas.*.insumos.*.cantidad' => 'required|numeric|min:0.01',
            'tareas.*.prerrequisitos' => 'array',
            'tareas.*.prerrequisitos.*' => 'string',
        ], [
            'direccionServicio.required_if' => 'La dirección del servicio es obligatoria para OT a domicilio.',
        ], [
            'clienteId' => 'cliente',
            'prioridadId' => 'prioridad',
            'direccionServicio' => 'dirección del servicio',
            'tareas.*.descripcion' => 'descripción de la tarea',
            'tareas.*.tecnico_id' => 'técnico',
            'tareas.*.dias_cumplimiento' => 'plazo de la tarea',
            'tareas.*.insumos.*.inventario_id' => 'insumo',
            'tareas.*.insumos.*.cantidad' => 'cantidad de insumo',
        ]);

        try {
            $ot = $servicio->crear(auth()->user(), [
                'cliente_id' => (int) $datos['clienteId'],
                'prioridad_id' => (int) $datos['prioridadId'],
                'tipo_servicio' => $datos['tipoServicio'],
                'direccion_servicio' => $datos['tipoServicio'] === 'domicilio' ? ($datos['direccionServicio'] ?: null) : null,
                'descripcion' => $datos['descripcion'],
                'tiempo_estimado_dias' => $datos['tiempoEstimadoDias'] !== '' ? (float) $datos['tiempoEstimadoDias'] : null,
                // Defensa en profundidad: aunque el campo esté oculto para el Jefe de Taller,
                // el valor del proyecto solo lo puede fijar el Administrador (ver manageCosteo).
                'valor_proyecto' => auth()->user()->hasRole(RolPrioridad::Administrador->value) && $datos['valorProyecto'] !== ''
                    ? (float) $datos['valorProyecto']
                    : null,
                'equipo_descripcion' => $this->equipoDescripcion ?: null,
                'equipo_marca' => $this->equipoMarca ?: null,
                'equipo_modelo' => $this->equipoModelo ?: null,
                'equipo_serie' => $this->equipoSerie ?: null,
                'equipo_estado_ingreso' => $this->equipoEstadoIngreso ?: null,
            ], $this->tareas);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }

            return;
        }

        if ($this->fotoEntrada) {
            $ruta = $this->fotoEntrada->store('evidencias-ot', 'public');
            $ot->evidencias()->create([
                'tipo_registro' => 'entrada',
                'tipo_archivo' => $this->fotoEntrada->getMimeType(),
                'url_archivo' => $ruta,
                'descripcion' => 'Registro fotográfico de entrada',
                'subida_por' => auth()->id(),
                'fecha_subida' => now(),
            ]);
            $ot->registrarEvento('evidencia', 'Registro fotográfico de entrada cargado.');
        }

        session()->flash('ok', 'OT '.$ot->numero_ot.' creada.');
        $this->redirectRoute('ordenes-trabajo.detalle', $ot, navigate: false);
    }
}; ?>

<div class="w-full flex flex-col gap-6">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 sm:p-8 flex flex-col gap-9">

        {{-- Datos generales --}}
        <section class="flex flex-col gap-5">
            <header>
                <h2 class="text-[15px] font-bold font-display">Datos generales</h2>
                <p class="text-xs text-slate-400 mt-0.5">Cliente, prioridad y alcance del servicio.</p>
            </header>

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                <x-field label="Cliente" required>
                    <x-select wire:model="clienteId">
                        @foreach ($clientes as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                        @endforeach
                    </x-select>
                    @error('clienteId') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                </x-field>

                <x-field label="Prioridad" required>
                    <x-select wire:model="prioridadId" :placeholder="null">
                        @foreach ($prioridades as $p)
                            <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                        @endforeach
                    </x-select>
                    @error('prioridadId') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                </x-field>

                <x-field label="Tipo de servicio" required>
                    <x-select wire:model="tipoServicio" :placeholder="null">
                        <option value="taller">Taller</option>
                        <option value="domicilio">Domicilio</option>
                    </x-select>
                </x-field>

                <x-field label="Tiempo estimado (días)">
                    <x-input type="number" step="0.5" min="0" wire:model.live="tiempoEstimadoDias" placeholder="Ej. 6" />
                    @error('tiempoEstimadoDias') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                </x-field>

                <x-field label="Dirección del servicio" class="sm:col-span-2 xl:col-span-4" x-show="$wire.tipoServicio === 'domicilio'" x-cloak
                         hint="Obligatoria para servicios a domicilio.">
                    <x-input wire:model="direccionServicio" placeholder="Dónde se presta el servicio" />
                    @error('direccionServicio') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                </x-field>

                <x-field label="Descripción del servicio" required class="sm:col-span-2 {{ $puedeDefinirValor ? 'xl:col-span-3' : 'xl:col-span-4' }}">
                    <x-textarea wire:model="descripcion" rows="3" placeholder="Detalle del trabajo solicitado por el cliente" />
                    @error('descripcion') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                </x-field>

                @if ($puedeDefinirValor)
                    <x-field label="Valor del proyecto" hint="Puede definirse o corregirse después desde el costeo.">
                        <x-input type="number" step="1" min="0" wire:model="valorProyecto" placeholder="$ 0" />
                        @error('valorProyecto') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                    </x-field>
                @endif
            </div>
        </section>

        {{-- Equipo --}}
        <section class="flex flex-col gap-5 border-t border-slate-100 dark:border-slate-800 pt-8">
            <header>
                <h2 class="text-[15px] font-bold font-display">Equipo (recepción)</h2>
                <p class="text-xs text-slate-400 mt-0.5">Datos y estado del equipo al ingresar al taller.</p>
            </header>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-field label="Tipo de equipo">
                    <x-input wire:model="equipoDescripcion" placeholder="Compresor, escalera…" />
                </x-field>
                <x-field label="Marca">
                    <x-input wire:model="equipoMarca" placeholder="Marca" />
                </x-field>
                <x-field label="Modelo">
                    <x-input wire:model="equipoModelo" placeholder="Modelo" />
                </x-field>
                <x-field label="Serie">
                    <x-input wire:model="equipoSerie" placeholder="Número de serie" />
                </x-field>
                <x-field label="Estado de ingreso del equipo" class="sm:col-span-2 lg:col-span-3">
                    <x-input wire:model="equipoEstadoIngreso" placeholder="Cómo llegó el equipo (golpes, faltantes, etc.)" />
                </x-field>
                <x-field label="Registro fotográfico de entrada">
                    <div class="flex items-start gap-3">
                        @if ($fotoEntrada && str((string) $fotoEntrada->getMimeType())->startsWith('image/'))
                            <div class="relative shrink-0">
                                <img src="{{ $fotoEntrada->temporaryUrl() }}" class="h-20 w-20 rounded-xl object-cover border border-slate-200 dark:border-slate-700">
                                <button type="button" wire:click="$set('fotoEntrada', null)"
                                        class="absolute -top-2 -right-2 w-5 h-5 rounded-full bg-brand-red text-white text-[11px] font-bold flex items-center justify-center shadow">✕</button>
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <input type="file" accept="image/*" wire:model="fotoEntrada"
                                   class="w-full text-xs text-slate-500 dark:text-slate-400 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-blue-tint file:px-3 file:py-2 file:text-[12px] file:font-semibold file:text-brand-blue hover:file:bg-brand-blue/15 dark:file:bg-brand-navy-active dark:file:text-white cursor-pointer">
                            <div wire:loading wire:target="fotoEntrada" class="text-xs text-slate-400 mt-1">Cargando previsualización…</div>
                        </div>
                    </div>
                    @error('fotoEntrada') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                </x-field>
            </div>
        </section>

        {{-- Tareas --}}
        <section class="flex flex-col gap-4 border-t border-slate-100 dark:border-slate-800 pt-8">
            <header class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-[15px] font-bold font-display">Tareas <span class="text-brand-red">*</span></h2>
                    <p class="text-xs text-slate-400 mt-0.5">Cada tarea necesita un técnico responsable. Al elegir un insumo se genera una solicitud a Bodega.</p>
                    @php $sumaPlazos = collect($tareas)->sum(fn ($t) => (float) ($t['dias_cumplimiento'] ?? 0)); $estim = (float) ($tiempoEstimadoDias ?: 0); @endphp
                    @if ($sumaPlazos > 0 || $estim > 0)
                        <p class="text-xs mt-1 {{ $estim > 0 && $sumaPlazos > $estim ? 'text-brand-red font-semibold' : 'text-slate-400' }}">
                            Plazos asignados: {{ rtrim(rtrim(number_format($sumaPlazos, 2), '0'), '.') }} / {{ $estim > 0 ? rtrim(rtrim(number_format($estim, 2), '0'), '.').' día(s) estimados' : 'sin estimado' }}
                        </p>
                    @endif
                </div>
                <button wire:click="agregarTarea" type="button"
                        class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-brand-blue/30 bg-brand-blue-tint dark:bg-brand-navy-active px-3 py-2 text-[12.5px] font-semibold text-brand-blue dark:text-white hover:bg-brand-blue/15 transition">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Agregar tarea
                </button>
            </header>
            @error('tareas') <p class="text-xs font-medium text-brand-red">{{ $message }}</p> @enderror
            @error('tarea') <p class="text-xs font-medium text-brand-red">{{ $message }}</p> @enderror
            @error('dias_cumplimiento') <p class="text-xs font-medium text-brand-red">{{ $message }}</p> @enderror
            @error('prerrequisitos') <p class="text-xs font-medium text-brand-red">{{ $message }}</p> @enderror

            <div class="flex flex-col gap-4">
                @foreach ($tareas as $i => $tarea)
                    <div wire:key="tarea-{{ $tarea['uid'] ?? $i }}"
                         class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 p-4 sm:p-5 flex flex-col gap-4">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Tarea {{ $i + 1 }}</span>
                            @if (count($tareas) > 1)
                                <button wire:click="quitarTarea({{ $i }})" type="button"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-slate-400 hover:text-brand-red transition">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                    Quitar
                                </button>
                            @endif
                        </div>

                        <div class="flex flex-col gap-4">
                            <x-field label="Descripción" required>
                                <x-input wire:model="tareas.{{ $i }}.descripcion" placeholder="Qué se va a hacer" />
                                @error('tareas.'.$i.'.descripcion') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                            </x-field>

                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <x-field label="Técnico" required class="lg:col-span-2">
                                    <x-select wire:model="tareas.{{ $i }}.tecnico_id" :reset-key="'tec-'.($tarea['uid'] ?? $i)">
                                        @foreach ($tecnicos as $t)
                                            <option value="{{ $t['id'] }}">{{ $t['nombre'] }}</option>
                                        @endforeach
                                    </x-select>
                                    @error('tareas.'.$i.'.tecnico_id') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                                </x-field>

                                <x-field label="Plazo (días)" hint="Debe caber en el tiempo estimado.">
                                    <x-input type="number" step="0.5" min="0.5" wire:model.live="tareas.{{ $i }}.dias_cumplimiento" placeholder="Ej. 2" />
                                    @error('tareas.'.$i.'.dias_cumplimiento') <x-slot:error>{{ $message }}</x-slot:error> @enderror
                                </x-field>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 rounded-lg border border-slate-200/70 dark:border-slate-700/60 bg-white/50 dark:bg-slate-900/30 p-3">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Insumos requeridos (opcional)</span>
                                <button type="button" wire:click="agregarInsumo({{ $i }})" class="inline-flex items-center gap-1 text-[11px] font-semibold text-brand-blue hover:underline">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                    Agregar insumo
                                </button>
                            </div>
                            @forelse ($tarea['insumos'] ?? [] as $li => $linea)
                                <div wire:key="{{ ($tarea['uid'] ?? $i).'-ins-'.$li }}" class="grid grid-cols-[1fr_6rem_auto] gap-2 items-start">
                                    <x-select wire:model="tareas.{{ $i }}.insumos.{{ $li }}.inventario_id" :reset-key="'ins-'.($tarea['uid'] ?? $i).'-'.$li">
                                        @foreach ($insumos as $ins)
                                            <option value="{{ $ins['id'] }}">{{ $ins['nombre'] }} · disp. {{ rtrim(rtrim(number_format((float) $ins['disponible'], 2), '0'), '.') }}</option>
                                        @endforeach
                                    </x-select>
                                    <x-input type="number" step="0.01" min="0.01" placeholder="Cant." wire:model="tareas.{{ $i }}.insumos.{{ $li }}.cantidad" />
                                    <button type="button" wire:click="quitarInsumo({{ $i }}, {{ $li }})" class="h-10 w-9 shrink-0 text-slate-400 hover:text-brand-red text-sm">✕</button>
                                    @error('tareas.'.$i.'.insumos.'.$li.'.inventario_id') <p class="col-span-3 text-xs text-brand-red">{{ $message }}</p> @enderror
                                    @error('tareas.'.$i.'.insumos.'.$li.'.cantidad') <p class="col-span-3 text-xs text-brand-red">{{ $message }}</p> @enderror
                                </div>
                            @empty
                                <p class="text-[11px] text-slate-400">Sin insumos. La tarea no generará solicitudes a Bodega.</p>
                            @endforelse
                        </div>

                        @if ($i > 0)
                            <div class="flex flex-col gap-1.5 rounded-lg border border-slate-200/70 dark:border-slate-700/60 bg-white/50 dark:bg-slate-900/30 p-3">
                                <span class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Depende de (finalizar antes)</span>
                                <div class="flex flex-wrap gap-3">
                                    @foreach ($tareas as $j => $previa)
                                        @continue($j >= $i)
                                        <label wire:key="{{ ($tarea['uid'] ?? $i).'-dep-'.$j }}" class="inline-flex items-center gap-1.5 text-xs">
                                            <input type="checkbox" value="{{ $previa['uid'] }}" wire:model="tareas.{{ $i }}.prerrequisitos" class="rounded border-slate-300 dark:border-slate-600 text-brand-blue focus:ring-brand-blue/30">
                                            <span>Tarea {{ $j + 1 }}{{ $previa['descripcion'] ? ' — '.\Illuminate\Support\Str::limit($previa['descripcion'], 30) : '' }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Acciones --}}
        <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 dark:border-slate-800 pt-6">
            <button wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar"
                    class="inline-flex items-center justify-center h-11 px-6 rounded-xl bg-brand-blue hover:bg-brand-blue-dark text-white text-sm font-semibold transition disabled:opacity-60">
                <span wire:loading.remove wire:target="guardar">Crear OT</span>
                <span wire:loading wire:target="guardar">Creando…</span>
            </button>
            <a href="{{ route('ordenes-trabajo.tablero') }}" wire:navigate
               class="inline-flex items-center justify-center h-11 px-5 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                Cancelar
            </a>
        </div>
    </div>
</div>
