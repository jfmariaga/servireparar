<?php

use App\Livewire\Concerns\Notifies;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout', ['title' => 'Mi perfil'])] class extends Component
{
    use Notifies;

    public string $name = '';
    public string $telefono = '';

    public string $password_actual = '';
    public string $password_nueva = '';
    public string $password_nueva_confirmation = '';

    public function mount(): void
    {
        $this->name = auth()->user()->name;
        $this->telefono = (string) auth()->user()->telefono;
    }

    /**
     * FR-003: el usuario edita nombre/teléfono, nunca sus propios roles.
     */
    public function guardarDatos(): void
    {
        $this->validate([
            'name' => 'required|string|max:150',
            'telefono' => 'nullable|string|max:20',
        ]);

        auth()->user()->update([
            'name' => $this->name,
            'telefono' => $this->telefono,
        ]);

        $this->notifySuccess('Perfil actualizado correctamente.');
    }

    public function cambiarContrasena(): void
    {
        $this->validate([
            'password_actual' => 'required',
            'password_nueva' => 'required|min:8|confirmed',
        ]);

        if (! Hash::check($this->password_actual, auth()->user()->password)) {
            $this->addError('password_actual', 'La contraseña actual no es correcta.');

            return;
        }

        auth()->user()->update(['password' => Hash::make($this->password_nueva)]);

        $this->reset(['password_actual', 'password_nueva', 'password_nueva_confirmation']);
        $this->notifySuccess('Contraseña actualizada correctamente.');
    }
}; ?>

<div class="max-w-2xl flex flex-col gap-5">

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-7 flex items-center gap-5">
        <div class="w-16 h-16 rounded-full bg-brand-blue-tint dark:bg-brand-navy-active text-brand-blue dark:text-white flex items-center justify-center text-xl font-bold shrink-0">
            {{ collect(explode(' ', auth()->user()->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
        </div>
        <div class="min-w-0">
            <div class="text-lg font-bold truncate">{{ auth()->user()->name }}</div>
            <div class="text-sm text-slate-500 dark:text-slate-400 truncate">{{ auth()->user()->email }}</div>
            <div class="flex flex-wrap gap-1.5 mt-2">
                @forelse (auth()->user()->getRoleNames() as $rol)
                    <span class="inline-block px-2.5 py-1 rounded-full text-[11.5px] font-semibold bg-brand-blue-tint text-brand-blue dark:bg-brand-navy-active dark:text-white">{{ $rol }}</span>
                @empty
                    <span class="inline-block px-2.5 py-1 rounded-full text-[11.5px] font-semibold bg-slate-100 text-slate-500 dark:bg-slate-800">Sin rol asignado</span>
                @endforelse
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-7">
        <h2 class="font-bold mb-4">Información personal</h2>
        <form wire:submit="guardarDatos" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Nombre</label>
                    <input type="text" wire:model="name" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                    @error('name') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Teléfono</label>
                    <input type="text" wire:model="telefono" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 outline-none focus:border-brand-blue">
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-semibold text-slate-400 mb-1.5">Correo electrónico</label>
                    <input type="email" value="{{ auth()->user()->email }}" disabled
                           class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-slate-400 rounded-lg px-3.5 py-2">
                    <p class="text-xs text-slate-400 mt-1">Solo el Administrador puede cambiar tu correo.</p>
                </div>
            </div>
            <button type="submit" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Guardar cambios
            </button>
        </form>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-7">
        <h2 class="font-bold mb-4">Cambiar contraseña</h2>
        <form wire:submit="cambiarContrasena" class="space-y-4 max-w-sm">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Contraseña actual</label>
                <input type="password" wire:model="password_actual" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 text-sm outline-none focus:border-brand-blue">
                @error('password_actual') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Nueva contraseña</label>
                <input type="password" wire:model="password_nueva" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 text-sm outline-none focus:border-brand-blue">
                @error('password_nueva') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Confirmar nueva contraseña</label>
                <input type="password" wire:model="password_nueva_confirmation" class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3.5 py-2 text-sm outline-none focus:border-brand-blue">
            </div>
            <button type="submit" class="bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition">
                Actualizar contraseña
            </button>
        </form>
    </div>
</div>
