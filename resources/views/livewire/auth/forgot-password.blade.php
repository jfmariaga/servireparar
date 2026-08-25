<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout')] class extends Component
{
    public string $email = '';
    public bool $enviado = false;

    /**
     * Mensaje siempre genérico: no revela si el correo existe en el sistema
     * (mismo principio de seguridad que el login, spec 001 Acceptance Scenario 2).
     */
    public function enviar(): void
    {
        $this->validate(['email' => 'required|email']);

        Password::sendResetLink(['email' => $this->email]);

        $this->enviado = true;
    }
}; ?>

<div class="min-h-screen flex bg-slate-100 dark:bg-slate-950">

    <div class="hidden lg:flex lg:w-[480px] xl:w-[560px] shrink-0 bg-brand-navy relative overflow-hidden flex-col justify-between p-14">
        <svg class="absolute -top-20 -right-28 w-[420px] h-[420px] opacity-50" viewBox="0 0 200 200" fill="none">
            <circle cx="100" cy="100" r="90" stroke="#2648d6" stroke-width="1"/>
            <circle cx="100" cy="100" r="60" stroke="#2648d6" stroke-width="1"/>
            <circle cx="100" cy="100" r="30" stroke="#e0332c" stroke-width="1.5"/>
        </svg>

        <div class="relative bg-white rounded-lg px-4 py-2.5 w-fit">
            <img src="{{ asset('img/logo.png') }}" alt="ServiReparar" class="h-[26px] w-auto block">
        </div>

        <div class="relative">
            <h1 class="text-white text-3xl font-semibold leading-tight mb-4">Recupera el acceso<br>a tu cuenta.</h1>
            <p class="text-slate-400 text-[15px] leading-relaxed max-w-sm">Te enviaremos un enlace de un solo uso a tu correo registrado para restablecer tu contraseña.</p>
        </div>

        <div class="relative text-slate-500 text-xs">© {{ now()->year }} SERVIREPARAR S.A.S</div>
    </div>

    <div class="flex-1 flex items-center justify-center p-10">
        <div class="w-full max-w-sm">
            <h2 class="text-2xl font-bold mb-1.5">¿Olvidaste tu contraseña?</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-8">Ingresa tu correo y te enviaremos un enlace para restablecerla.</p>

            @if ($enviado)
                <div class="mb-5 rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 text-sm px-3.5 py-2.5">
                    Si el correo <strong>{{ $email }}</strong> está registrado, te enviamos un enlace para restablecer tu contraseña. Revisa tu bandeja de entrada.
                </div>
            @else
                <form wire:submit="enviar" class="space-y-4">
                    <div>
                        <label class="block text-[13px] font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Correo electrónico</label>
                        <input type="email" wire:model="email" required autofocus
                               class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-900 rounded-lg px-3.5 py-2.5 text-sm outline-none focus:border-brand-blue focus:ring-2 focus:ring-brand-blue-tint dark:focus:ring-0">
                        @error('email') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit" wire:loading.attr="disabled" wire:target="enviar"
                            class="w-full bg-brand-blue hover:bg-brand-blue-dark text-white rounded-lg py-3 text-[15px] font-semibold transition disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-2.5">
                        <svg wire:loading wire:target="enviar" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"/>
                            <path class="opacity-90" d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                        <span wire:loading.remove wire:target="enviar">Enviar enlace</span>
                        <span wire:loading wire:target="enviar">Enviando…</span>
                    </button>
                </form>
            @endif

            <p class="text-sm text-slate-500 dark:text-slate-400 mt-6">
                <a href="{{ route('login') }}" class="text-brand-blue font-semibold hover:underline">← Volver a iniciar sesión</a>
            </p>
        </div>
    </div>
</div>
