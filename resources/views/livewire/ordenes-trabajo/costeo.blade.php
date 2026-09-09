<?php

use App\Livewire\Concerns\Notifies;
use App\Models\Contratista;
use App\Models\OrdenTrabajo;
use App\Services\OrdenTrabajo\CosteoOtService;
use App\Support\Moneda;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Costeo de OT'])] class extends Component
{
    use Notifies;

    public OrdenTrabajo $ot;

    public string $valorProyecto = '';

    // Alta de mano de obra de contratista
    public ?int $contratistaId = null;
    public string $especialidad = '';
    public string $cantidad = '1';
    public string $valor = '';

    public function mount(OrdenTrabajo $ordenTrabajo): void
    {
        $this->ot = $ordenTrabajo;
        Gate::authorize('viewCosteo', $this->ot);
        $this->valorProyecto = (string) ($this->ot->valor_proyecto ?? '');
    }

    public function with(): array
    {
        $this->ot->load(['manoObraContratistas.contratista', 'tareas.tecnico.usuario']);

        return [
            'costeo' => app(CosteoOtService::class)->calcular($this->ot),
            'contratistas' => Contratista::where('estado', 'activo')->orderBy('nombre')->get(['id', 'nombre']),
            'Moneda' => Moneda::class,
        ];
    }

    public function guardarValorProyecto(): void
    {
        Gate::authorize('viewCosteo', $this->ot);
        $this->validate(['valorProyecto' => 'nullable|numeric|min:0'], [], ['valorProyecto' => 'valor del proyecto']);

        $this->ot->update(['valor_proyecto' => $this->valorProyecto !== '' ? (float) $this->valorProyecto : null]);
        $this->ot->registrarEvento('correccion', 'Valor del proyecto actualizado.', auth()->user());
        $this->notifySuccess('Valor del proyecto actualizado.');
    }

    public function agregarContratista(): void
    {
        Gate::authorize('viewCosteo', $this->ot);
        $datos = $this->validate([
            'contratistaId' => 'required|exists:contratistas,id',
            'especialidad' => 'nullable|string|max:100',
            'cantidad' => 'required|numeric|min:0.01',
            'valor' => 'required|numeric|min:0',
        ], [], ['contratistaId' => 'contratista']);

        $this->ot->manoObraContratistas()->create([
            'contratista_id' => (int) $datos['contratistaId'],
            'especialidad' => $this->especialidad ?: null,
            'cantidad' => (float) $datos['cantidad'],
            'valor' => (float) $datos['valor'],
        ]);
        $this->ot->registrarEvento('correccion', 'Mano de obra de contratista agregada al costeo.', auth()->user());
        $this->reset('contratistaId', 'especialidad', 'valor');
        $this->cantidad = '1';
        $this->notifySuccess('Contratista agregado.');
    }

    public function quitarContratista(int $id): void
    {
        Gate::authorize('viewCosteo', $this->ot);
        $this->ot->manoObraContratistas()->whereKey($id)->delete();
        $this->notifySuccess('Línea de contratista eliminada.');
    }
}; ?>

<div class="max-w-3xl flex flex-col gap-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('ordenes-trabajo.detalle', $ot) }}" wire:navigate class="text-sm text-brand-blue hover:underline">← {{ $ot->numero_ot }}</a>
        <h1 class="text-lg font-bold">Costeo y utilidad neta</h1>
    </div>

    {{-- Mano de obra propia --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-2 text-sm">
        <h2 class="font-bold text-sm mb-1">Mano de obra Servireparar (por días)</h2>
        <p class="text-xs text-slate-400">Sueldo vigente a la fecha de referencia: {{ $costeo['fecha_referencia']->format('d/m/Y') }}</p>
        @foreach ($ot->tareas as $tarea)
            <div class="flex justify-between border-b border-slate-50 dark:border-slate-800/60 py-1.5">
                <span>{{ $tarea->tecnico?->usuario?->name ?? 'Técnico #'.$tarea->tecnico_id }} — {{ $tarea->descripcion }}</span>
                <span class="text-slate-500">{{ rtrim(rtrim(number_format((float) ($tarea->dias_trabajados ?? 0), 2), '0'), '.') }} día(s) × {{ $Moneda::cop($tarea->tecnico?->valorDia($costeo['fecha_referencia'])) }}</span>
            </div>
        @endforeach
        <div class="flex justify-between font-semibold pt-1">
            <span>Subtotal mano de obra propia</span><span>{{ $Moneda::cop($costeo['mano_obra_propia']) }}</span>
        </div>
    </div>

    {{-- Mano de obra contratista --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-2 text-sm">
        <h2 class="font-bold text-sm mb-1">Mano de obra contratista</h2>
        @forelse ($ot->manoObraContratistas as $mo)
            <div wire:key="mo-{{ $mo->id }}" class="flex justify-between items-center border-b border-slate-50 dark:border-slate-800/60 py-1.5">
                <span>{{ $mo->contratista?->nombre }} @if ($mo->especialidad)· {{ $mo->especialidad }}@endif · x{{ rtrim(rtrim(number_format((float) $mo->cantidad, 2), '0'), '.') }}</span>
                <span class="flex items-center gap-3">
                    <span class="text-slate-500">{{ $Moneda::cop($mo->valor) }}</span>
                    <button wire:click="quitarContratista({{ $mo->id }})" class="text-xs text-slate-400 hover:text-brand-red">✕</button>
                </span>
            </div>
        @empty
            <p class="text-xs text-slate-400">Sin contratistas registrados.</p>
        @endforelse
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 pt-2">
            <select wire:model="contratistaId" class="border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-2 py-2">
                <option value="">Contratista…</option>
                @foreach ($contratistas as $c)<option value="{{ $c->id }}">{{ $c->nombre }}</option>@endforeach
            </select>
            <input type="text" wire:model="especialidad" placeholder="Especialidad" class="border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-2 py-2">
            <input type="number" step="0.01" min="0.01" wire:model="cantidad" placeholder="Cant." class="border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-2 py-2">
            <input type="number" step="1" min="0" wire:model="valor" placeholder="Valor" class="border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-2 py-2">
        </div>
        @error('contratistaId') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
        @error('valor') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
        <button wire:click="agregarContratista" class="self-start text-[12.5px] font-semibold px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Agregar contratista</button>
        <div class="flex justify-between font-semibold pt-1">
            <span>Subtotal contratistas</span><span>{{ $Moneda::cop($costeo['contratistas']) }}</span>
        </div>
    </div>

    {{-- Resumen --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 flex flex-col gap-2 text-sm">
        <div class="flex justify-between"><span>Repuestos / insumos</span><span>{{ $Moneda::cop($costeo['repuestos']) }}</span></div>
        @if (($costeo['repuestos_estimados'] ?? 0) > 0)
            <div class="flex justify-between text-[11px] text-amber-600 dark:text-amber-400"><span>· incluye estimado (aún no despachado por Bodega)</span><span>{{ $Moneda::cop($costeo['repuestos_estimados']) }}</span></div>
        @endif
        <div class="flex justify-between"><span>Mano de obra propia</span><span>{{ $Moneda::cop($costeo['mano_obra_propia']) }}</span></div>
        <div class="flex justify-between"><span>Mano de obra contratista</span><span>{{ $Moneda::cop($costeo['contratistas']) }}</span></div>
        <div class="flex justify-between font-bold border-t border-slate-200 dark:border-slate-700 pt-2 mt-1"><span>Costo total del proyecto</span><span>{{ $Moneda::cop($costeo['costo_total']) }}</span></div>

        <div class="flex items-center gap-2 pt-3">
            <label class="font-semibold">Valor del proyecto (cliente)</label>
            <input type="number" step="1" min="0" wire:model="valorProyecto" class="w-40 border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-1.5">
            <button wire:click="guardarValorProyecto" class="text-[12px] font-semibold px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Guardar</button>
        </div>
        @error('valorProyecto') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror

        <div class="flex justify-between font-bold text-base pt-2 {{ $costeo['utilidad_neta'] < 0 ? 'text-brand-red' : 'text-emerald-600 dark:text-emerald-400' }}">
            <span>Utilidad neta del proyecto</span><span>{{ $Moneda::cop($costeo['utilidad_neta']) }}</span>
        </div>
        @if ($costeo['valor_proyecto'] === null)
            <p class="text-xs text-slate-400">El valor del proyecto aún no se ha definido; la utilidad se muestra contra $ 0.</p>
        @endif
    </div>
</div>
