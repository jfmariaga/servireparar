<?php

use App\Enums\RolPrioridad;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout')] class extends Component
{
    public string $nickuser = '';
    public string $password = '';
    public bool $remember = false;
    public string $errorMessage = '';

    public function login(): void
    {
        $this->errorMessage = '';

        $this->validate([
            'nickuser' => 'required|string',
            'password' => 'required',
        ]);

        if (! Auth::attempt(['nickuser' => $this->nickuser, 'password' => $this->password], $this->remember)) {
            // Mensaje genérico: no revela si el usuario existe (spec 001, Acceptance Scenario 2)
            $this->errorMessage = 'Las credenciales no coinciden con nuestros registros.';

            return;
        }

        $user = Auth::user();

        if (! $user->isActivo()) {
            Auth::logout();
            $this->errorMessage = 'Tu cuenta está inactiva. Contacta al Administrador.';

            return;
        }

        session()->regenerate();

        $rolPrioritario = collect(RolPrioridad::ordenados())
            ->first(fn ($rol) => $user->hasRole($rol));

        $this->redirect(
            $rolPrioritario ? route(RolPrioridad::rutaDashboard($rolPrioritario)) : route('dashboard'),
            navigate: false
        );
    }
}; ?>

<div class="min-h-screen flex bg-slate-100 dark:bg-slate-950">

    <div class="hidden lg:flex lg:w-[480px] xl:w-[560px] shrink-0 bg-brand-navy relative overflow-hidden flex-col justify-between p-14">
        <svg class="absolute -top-20 -right-28 w-[420px] h-[420px] opacity-50" viewBox="0 0 200 200" fill="none">
            <circle cx="100" cy="100" r="90" stroke="#2648d6" stroke-width="1"/>
            <circle cx="100" cy="100" r="60" stroke="#2648d6" stroke-width="1"/>
            <circle cx="100" cy="100" r="30" stroke="#e0332c" stroke-width="1.5"/>
        </svg>

        <div class="relative bg-white rounded-lg px-6 py-4 w-fit">
            <img src="{{ asset('img/logo.png') }}" alt="ServiReparar" class="h-12 w-auto block">
        </div>

        <div class="relative">
            <h1 class="text-white text-3xl font-semibold leading-tight mb-4">Control total de tu<br>operación de taller.</h1>
            <p class="text-slate-400 text-[15px] leading-relaxed max-w-sm">Órdenes de trabajo, inventario y cotizaciones en un solo lugar, con trazabilidad completa de principio a fin.</p>
        </div>

        <div class="relative text-slate-500 text-xs">© {{ now()->year }} SERVIREPARAR S.A.S</div>
    </div>

    <div class="flex-1 flex items-center justify-center p-10">
        <div class="w-full max-w-sm">
            <div class="lg:hidden mb-8 mx-auto bg-white rounded-lg px-5 py-3 w-fit shadow-sm">
                <img src="{{ asset('img/logo.png') }}" alt="ServiReparar" class="h-10 w-auto block">
            </div>

            <h2 class="text-2xl font-bold mb-1.5">Iniciar sesión</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-8">Ingresa con las credenciales asignadas por tu administrador.</p>

            @if ($errorMessage)
                <div class="mb-5 rounded-lg bg-brand-red-tint text-brand-red text-sm px-3.5 py-2.5">
                    {{ $errorMessage }}
                </div>
            @endif

            <form wire:submit="login" class="space-y-4">
                <div>
                    <label class="block text-[13px] font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Usuario</label>
                    <input type="text" wire:model="nickuser" required autofocus autocomplete="username"
                           class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-900 rounded-lg px-3.5 py-2.5 text-sm outline-none focus:border-brand-blue focus:ring-2 focus:ring-brand-blue-tint dark:focus:ring-0">
                    @error('nickuser') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>

                <div x-data="{ mostrar: false }">
                    <label class="block text-[13px] font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Contraseña</label>
                    <div class="relative">
                        <input :type="mostrar ? 'text' : 'password'" wire:model="password" required
                               class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-900 rounded-lg px-3.5 py-2.5 pr-10 text-sm outline-none focus:border-brand-blue focus:ring-2 focus:ring-brand-blue-tint dark:focus:ring-0">
                        <button type="button" @click="mostrar = !mostrar" tabindex="-1"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <svg x-show="!mostrar" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.75 7.5-9.75 7.5S2.25 12 2.25 12z"/>
                                <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <svg x-show="mostrar" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12c1.292 4.338 5.31 7.5 10.065 7.5a10.45 10.45 0 005.233-1.4m2.148-1.673A10.49 10.49 0 0022.066 12c-1.292-4.338-5.31-7.5-10.066-7.5a10.46 10.46 0 00-4.064.81M6.228 6.228l11.544 11.544M6.228 6.228L3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 10-4.243-4.243"/>
                            </svg>
                        </button>
                    </div>
                    @error('password') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-[13px] text-slate-600 dark:text-slate-300">
                        <input type="checkbox" wire:model="remember" class="accent-brand-blue">
                        Recordarme
                    </label>
                </div>

                <p class="text-[12px] text-slate-400 text-center">¿Olvidaste tu contraseña? Pídele a un Administrador que te la restablezca.</p>

                <button type="submit" wire:loading.attr="disabled" wire:target="login"
                        class="w-full bg-brand-blue hover:bg-brand-blue-dark text-white rounded-lg py-3 text-[15px] font-semibold transition disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-2.5">
                    <svg wire:loading wire:target="login" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"/>
                        <path class="opacity-90" d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                    <span wire:loading.remove wire:target="login">Acceder</span>
                    <span wire:loading wire:target="login">Ingresando…</span>
                </button>
            </form>
        </div>
    </div>
</div>
