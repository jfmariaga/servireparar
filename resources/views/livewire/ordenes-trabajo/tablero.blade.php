<?php

use App\Models\Cliente;
use App\Models\EstadoOt;
use App\Models\OrdenTrabajo;
use App\Models\Tecnico;
use App\Services\Reportes\ExcelExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layout', ['title' => 'Órdenes de trabajo'])] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $buscar = '';

    #[Url]
    public string $estado = '';

    #[Url]
    public string $cliente = '';

    #[Url]
    public string $tipo = '';

    #[Url]
    public string $tecnico = '';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', OrdenTrabajo::class);
    }

    public function updating($campo): void
    {
        if (in_array($campo, ['buscar', 'estado', 'cliente', 'tipo', 'tecnico', 'desde', 'hasta'], true)) {
            $this->resetPage();
        }
    }

    public function limpiar(): void
    {
        $this->reset('buscar', 'estado', 'cliente', 'tipo', 'tecnico', 'desde', 'hasta');
        $this->resetPage();
    }

    /**
     * Query filtrada compartida entre la paginación en pantalla y las
     * exportaciones (spec 007, US3, FR-007): así el Excel/PDF nunca puede
     * mostrar algo distinto de lo que el usuario ve filtrado en el tablero.
     */
    private function otsFiltradas(): Builder
    {
        return OrdenTrabajo::query()
            ->visiblesPara(auth()->user())
            ->with(['cliente:id,nombre', 'estado', 'prioridad:id,nombre'])
            ->buscar($this->buscar)
            ->when($this->estado !== '', fn ($q) => $q->whereHas('estado', fn ($e) => $e->where('slug', $this->estado)))
            ->when($this->cliente !== '', fn ($q) => $q->where('cliente_id', $this->cliente))
            ->when($this->tipo !== '', fn ($q) => $q->where('tipo_servicio', $this->tipo))
            ->when($this->tecnico !== '', fn ($q) => $q->whereHas('tareas', fn ($t) => $t->where('tecnico_id', $this->tecnico)))
            ->when($this->desde !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->desde))
            ->when($this->hasta !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->hasta))
            ->latest('id');
    }

    public function exportarExcel(ExcelExportService $excel)
    {
        $filas = $this->otsFiltradas()->get()->map(fn (OrdenTrabajo $ot) => [
            $ot->numero_ot,
            $ot->cliente?->nombre,
            $ot->descripcion,
            ucfirst($ot->tipo_servicio),
            $ot->prioridad?->nombre,
            $ot->estado?->nombre,
            $ot->created_at?->format('d/m/Y'),
        ]);

        return $excel->descargar(
            'ordenes-trabajo-'.now()->format('Y-m-d-His').'.xlsx',
            ['N.º', 'Cliente', 'Descripción', 'Tipo', 'Prioridad', 'Estado', 'Creada'],
            $filas,
        );
    }

    public function exportarPdf()
    {
        $pdf = Pdf::loadView('pdf.ordenes-trabajo', ['ots' => $this->otsFiltradas()->get()]);

        // Livewire solo detecta la descarga si la acción devuelve un
        // StreamedResponse/BinaryFileResponse; el Response plano de dompdf no
        // dispara la descarga en el navegador, por eso se envuelve así.
        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'ordenes-trabajo-'.now()->format('Y-m-d-His').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function with(): array
    {
        $ots = $this->otsFiltradas()->paginate(15);

        return [
            'ots' => $ots,
            'estados' => EstadoOt::orderBy('orden')->get(),
            'clientes' => Cliente::activos()->orderBy('nombre')->get(['id', 'nombre']),
            'tecnicos' => Tecnico::disponibles()->with('usuario:id,name')->get()
                ->map(fn (Tecnico $t) => ['id' => $t->id, 'nombre' => $t->usuario?->name ?? 'Técnico #'.$t->id]),
            'puedeCrear' => Gate::allows('create', OrdenTrabajo::class),
        ];
    }
}; ?>

<div class="flex flex-col gap-5">
    <x-breadcrumbs :items="[['label' => 'Órdenes de trabajo']]" />

    <div class="flex items-center justify-between">
        <h1 class="text-lg font-bold">Órdenes de trabajo</h1>
        @if ($puedeCrear)
            <a href="{{ route('ordenes-trabajo.crear') }}" wire:navigate class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13px] font-semibold px-3.5 py-2 rounded-lg transition">+ Nueva OT</a>
        @endif
    </div>

    @if (session('ok'))
        <div class="bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300 text-sm rounded-lg px-4 py-2.5">{{ session('ok') }}</div>
    @endif

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-3 text-sm">
        <input type="text" wire:model.live.debounce.400ms="buscar" placeholder="Buscar N.º, descripción o cliente" class="sm:col-span-2 border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
        <select wire:model.live="estado" class="border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
            <option value="">Todos los estados</option>
            @foreach ($estados as $e)
                <option value="{{ $e->slug }}">{{ $e->nombre }}</option>
            @endforeach
        </select>
        <select wire:model.live="cliente" class="border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
            <option value="">Todos los clientes</option>
            @foreach ($clientes as $c)
                <option value="{{ $c->id }}">{{ $c->nombre }}</option>
            @endforeach
        </select>
        <select wire:model.live="tipo" class="border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
            <option value="">Taller y domicilio</option>
            <option value="taller">Taller</option>
            <option value="domicilio">Domicilio</option>
        </select>
        <select wire:model.live="tecnico" class="border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
            <option value="">Todos los técnicos</option>
            @foreach ($tecnicos as $t)
                <option value="{{ $t['id'] }}">{{ $t['nombre'] }}</option>
            @endforeach
        </select>
        <div class="flex items-center gap-2">
            <input type="date" wire:model.live="desde" title="Creada desde" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-2 py-2 outline-none focus:border-brand-blue text-xs">
            <input type="date" wire:model.live="hasta" title="Creada hasta" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-2 py-2 outline-none focus:border-brand-blue text-xs">
        </div>
    </div>

    <div class="flex items-center justify-end gap-2 -mt-2">
        <button wire:click="exportarExcel" class="text-[12.5px] font-semibold px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center gap-1.5">
            Exportar Excel
        </button>
        <button wire:click="exportarPdf" class="text-[12.5px] font-semibold px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center gap-1.5">
            Exportar PDF
        </button>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                <tr>
                    <th class="px-4 py-3 font-semibold">N.º</th>
                    <th class="px-4 py-3 font-semibold">Cliente</th>
                    <th class="px-4 py-3 font-semibold">Descripción</th>
                    <th class="px-4 py-3 font-semibold">Tipo</th>
                    <th class="px-4 py-3 font-semibold">Prioridad</th>
                    <th class="px-4 py-3 font-semibold">Estado</th>
                    <th class="px-4 py-3 font-semibold">Creada</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ots as $ot)
                    <tr wire:key="ot-{{ $ot->id }}" class="border-b border-slate-50 dark:border-slate-800/60 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3">
                            <a href="{{ route('ordenes-trabajo.detalle', $ot) }}" wire:navigate class="font-semibold text-brand-blue hover:underline">{{ $ot->numero_ot }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $ot->cliente?->nombre }}</td>
                        <td class="px-4 py-3 max-w-[22rem] truncate text-slate-500 dark:text-slate-400">{{ $ot->descripcion }}</td>
                        <td class="px-4 py-3 capitalize">{{ $ot->tipo_servicio }}</td>
                        <td class="px-4 py-3">{{ $ot->prioridad?->nombre }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-0.5 text-xs font-semibold">{{ $ot->estado?->nombre }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-400">{{ $ot->created_at?->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Sin órdenes de trabajo que coincidan.
                        @if ($buscar || $estado || $cliente || $tipo || $tecnico || $desde || $hasta)
                            <button wire:click="limpiar" class="text-brand-blue hover:underline ml-1">Limpiar filtros</button>
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $ots->links() }}</div>
</div>
