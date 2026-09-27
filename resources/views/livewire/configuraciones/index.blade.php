<?php

use App\Livewire\Concerns\Notifies;
use App\Models\Configuracion;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Configuración'])] class extends Component
{
    use Notifies;

    /** @var array<string, string> */
    public array $valores = [];

    public function mount(): void
    {
        $this->valores = Configuracion::query()->pluck('valor', 'clave')->all();
    }

    public function with(): array
    {
        return [
            'configuraciones' => Configuracion::query()->orderBy('clave')->get(),
        ];
    }

    public function guardar(): void
    {
        $datos = $this->validate([
            'valores.*' => 'required|integer|min:0',
        ]);

        foreach ($datos['valores'] as $clave => $valor) {
            Configuracion::establecer($clave, (string) $valor);
        }

        $this->notifySuccess('Configuración actualizada correctamente.');
    }
}; ?>

<div>
    <x-breadcrumbs :items="[['label' => 'Configuración']]" />

    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6 max-w-2xl">
        Umbrales de alerta usados por el módulo de notificaciones. Cambiarlos aquí no requiere
        despliegue de código.
    </p>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden max-w-3xl">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b border-slate-100 dark:border-slate-800">
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">Umbral</th>
                    <th class="px-5 py-3 text-[11px] font-bold uppercase tracking-wide text-slate-400 w-32">Días</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($configuraciones as $config)
                    <tr class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                        <td class="px-5 py-3">
                            <p class="font-medium">{{ $config->descripcion }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <input type="number" min="0" wire:model="valores.{{ $config->clave }}"
                                   class="w-24 border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2 outline-none focus:border-brand-blue">
                            @error('valores.'.$config->clave) <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        <button wire:click="guardar" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
            Guardar
        </button>
    </div>
</div>
