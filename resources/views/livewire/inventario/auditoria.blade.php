<?php

use App\Livewire\Concerns\Notifies;
use App\Models\AjusteAuditoria;
use App\Models\AuditoriaInventario;
use App\Models\Inventario;
use App\Services\Inventario\MovimientoService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layout', ['title' => 'Auditoría de inventario'])] class extends Component
{
    use Notifies, WithPagination;

    public bool $mostrarFormConteo = false;
    public ?int $inventarioId = null;
    public string $stockFisico = '';
    public string $motivo = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', AuditoriaInventario::class);
    }

    public function with(): array
    {
        return [
            'auditoriaAbierta' => AuditoriaInventario::abiertas()->latest('fecha_inicio')->first(),
            'items' => Inventario::activos()->orderBy('nombre')->get(),
            'ajustesPendientes' => AjusteAuditoria::where('estado', 'pendiente')
                ->with('inventario')
                ->orderByDesc('id')
                ->paginate(10),
        ];
    }

    public function iniciarAuditoria(): void
    {
        Gate::authorize('create', AuditoriaInventario::class);

        AuditoriaInventario::create([
            'iniciada_por' => auth()->id(),
            'fecha_inicio' => now(),
            'estado' => 'abierta',
        ]);

        $this->notifySuccess('Auditoría iniciada.');
    }

    public function cerrarAuditoria(int $id): void
    {
        Gate::authorize('create', AuditoriaInventario::class);

        AuditoriaInventario::findOrFail($id)->update(['estado' => 'cerrada', 'fecha_cierre' => now()]);
        $this->notifySuccess('Auditoría cerrada.');
    }

    /**
     * Cancela una auditoría abierta por error — a diferencia de cerrarla, los
     * ajustes que hubiera pendientes quedan rechazados en vez de esperar aprobación.
     */
    public function cancelarAuditoria(int $id): void
    {
        Gate::authorize('create', AuditoriaInventario::class);

        $auditoria = AuditoriaInventario::findOrFail($id);
        $auditoria->update(['estado' => 'cancelada', 'fecha_cierre' => now()]);

        $auditoria->ajustes()->where('estado', 'pendiente')->get()->each(function (AjusteAuditoria $ajuste) {
            $ajuste->update([
                'estado' => 'rechazado',
                'motivo' => $ajuste->motivo.' (auditoría cancelada)',
                'resuelto_en' => now(),
            ]);
        });

        $this->notifySuccess('Auditoría cancelada. Los ajustes que estaban pendientes quedaron rechazados.');
    }

    public function nuevoConteo(): void
    {
        Gate::authorize('create', AuditoriaInventario::class);
        $this->reset(['inventarioId', 'stockFisico', 'motivo']);
        $this->mostrarFormConteo = true;
    }

    public function registrarConteo(): void
    {
        Gate::authorize('create', AuditoriaInventario::class);

        $auditoria = AuditoriaInventario::abiertas()->latest('fecha_inicio')->first();

        if (! $auditoria) {
            $this->addError('inventarioId', 'No hay una auditoría abierta. Inícia una primero.');

            return;
        }

        $datos = $this->validate([
            'inventarioId' => 'required|exists:inventario,id',
            'stockFisico' => 'required|numeric|min:0',
            'motivo' => 'required|string|max:255',
        ]);

        $item = Inventario::findOrFail($datos['inventarioId']);

        AjusteAuditoria::create([
            'auditoria_id' => $auditoria->id,
            'inventario_id' => $item->id,
            'stock_sistema' => $item->stock_actual,
            'stock_fisico' => $datos['stockFisico'],
            'motivo' => $datos['motivo'],
            'estado' => 'pendiente',
        ]);

        $this->mostrarFormConteo = false;
        $this->notifySuccess('Conteo registrado. El ajuste queda pendiente de aprobación del Administrador.');
    }

    public function cancelarConteo(): void
    {
        $this->mostrarFormConteo = false;
    }

    public function aprobar(int $ajusteId): void
    {
        Gate::authorize('approve', AjusteAuditoria::class);

        $ajuste = AjusteAuditoria::findOrFail($ajusteId);
        (new MovimientoService())->aplicarAjusteAprobado($ajuste, auth()->user());

        $this->notifySuccess('Ajuste aprobado: stock actualizado a '.$ajuste->stock_fisico.'.');
    }

    public function rechazar(int $ajusteId): void
    {
        Gate::authorize('approve', AjusteAuditoria::class);

        AjusteAuditoria::findOrFail($ajusteId)->update([
            'estado' => 'rechazado',
            'aprobado_por' => auth()->id(),
            'resuelto_en' => now(),
        ]);

        $this->notifySuccess('Ajuste rechazado. El stock no fue modificado.');
    }
}; ?>

<div class="flex flex-col gap-6">
    @include('partials.inventario-tabs')

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6">
        @if ($auditoriaAbierta)
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1">Auditoría en curso</div>
                    <div class="font-semibold">Iniciada el {{ $auditoriaAbierta->fecha_inicio->format('d/m/Y H:i') }} por {{ $auditoriaAbierta->iniciadaPor->name }}</div>
                </div>
                <div class="flex gap-2">
                    <button wire:click="nuevoConteo" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">Registrar conteo</button>
                    <button wire:click="cerrarAuditoria({{ $auditoriaAbierta->id }})" class="text-[13.5px] font-semibold px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Cerrar auditoría</button>
                    <button
                        x-on:click="Notify.confirmDanger({
                            title: '¿Cancelar auditoría?',
                            text: 'Los ajustes que estén pendientes de aprobación quedarán rechazados.',
                            confirmButtonText: 'Sí, cancelar',
                        }).then((ok) => ok && $wire.cancelarAuditoria({{ $auditoriaAbierta->id }}))"
                        class="text-[13.5px] font-semibold px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 text-brand-red hover:bg-brand-red-tint">Cancelar auditoría</button>
                </div>
            </div>
        @else
            <div class="flex items-center justify-between flex-wrap gap-3">
                <p class="text-sm text-slate-500 dark:text-slate-400">No hay una auditoría en curso. Se inician bajo demanda, sin periodicidad fija.</p>
                <button wire:click="iniciarAuditoria" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">Iniciar auditoría</button>
            </div>
        @endif
    </div>

    @if ($mostrarFormConteo)
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6">
            <h2 class="font-bold mb-4">Registrar conteo físico</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Ítem *</label>
                    <x-select wire:model="inventarioId">
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}">{{ $item->nombre }} ({{ $item->codigo }}) — sistema: {{ rtrim(rtrim(number_format((float) $item->stock_actual, 2), '0'), '.') }}</option>
                        @endforeach
                    </x-select>
                    @error('inventarioId') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Stock físico contado *</label>
                    <input type="number" step="0.01" min="0" wire:model="stockFisico" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('stockFisico') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Motivo *</label>
                    <input type="text" wire:model="motivo" placeholder="Ej. Conteo mensual de bodega" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('motivo') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="flex gap-2 mt-6">
                <button wire:click="registrarConteo" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">Registrar</button>
                <button wire:click="cancelarConteo" class="text-[13.5px] font-semibold px-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">Cancelar</button>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 font-bold text-sm">Ajustes pendientes de aprobación</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Ítem</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Sistema</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Físico</th>
                        <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Motivo</th>
                        @can('approve', \App\Models\AjusteAuditoria::class)
                            <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Aprobación</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ajustesPendientes as $ajuste)
                        <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                            <td class="px-5 py-3 whitespace-nowrap">{{ $ajuste->inventario->nombre }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $ajuste->stock_sistema, 2), '0'), '.') }}</td>
                            <td class="px-5 py-3 whitespace-nowrap font-semibold {{ $ajuste->diferencia() < 0 ? 'text-brand-red' : 'text-emerald-600' }}">{{ rtrim(rtrim(number_format((float) $ajuste->stock_fisico, 2), '0'), '.') }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $ajuste->motivo }}</td>
                            @can('approve', \App\Models\AjusteAuditoria::class)
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <button wire:click="aprobar({{ $ajuste->id }})" class="text-xs font-semibold text-white bg-brand-blue hover:bg-brand-blue-dark rounded-lg px-2.5 py-1.5">Aprobar</button>
                                        <button
                                            x-on:click="Notify.confirmDanger({
                                                title: '¿Rechazar ajuste?',
                                                text: 'El stock del sistema no se modificará.',
                                                confirmButtonText: 'Sí, rechazar',
                                            }).then((ok) => ok && $wire.rechazar({{ $ajuste->id }}))"
                                            class="text-xs font-semibold text-brand-red hover:underline">Rechazar</button>
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">No hay ajustes pendientes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3.5">{{ $ajustesPendientes->links() }}</div>
    </div>
</div>
