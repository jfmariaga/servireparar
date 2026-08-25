<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout')] class extends Component
{
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $errorMessage = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function restablecer(): void
    {
        $this->errorMessage = '';

        $this->validate([
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user) {
                $user->forceFill(['password' => Hash::make($this->password)])->save();

                // Invalida cualquier sesión previa del usuario (independent test de spec 001, US2).
                DB::table('sessions')->where('user_id', $user->id)->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->errorMessage = 'El enlace no es válido o ya expiró. Solicita uno nuevo.';

            return;
        }

        $this->redirect(route('login'), navigate: false);
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
            <h1 class="text-white text-3xl font-semibold leading-tight mb-4">Elige una nueva<br>contraseña.</h1>
            <p class="text-slate-400 text-[15px] leading-relaxed max-w-sm">Por seguridad, cerraremos las sesiones activas de tu cuenta al confirmar el cambio.</p>
        </div>

        <div class="relative text-slate-500 text-xs">© {{ now()->year }} SERVIREPARAR S.A.S</div>
    </div>

    <div class="flex-1 flex items-center justify-center p-10">
        <div class="w-full max-w-sm">
            <h2 class="text-2xl font-bold mb-1.5">Restablecer contraseña</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-8">Ingresa tu nueva contraseña para <strong>{{ $email }}</strong>.</p>

            @if ($errorMessage)
                <div class="mb-5 rounded-lg bg-brand-red-tint text-brand-red text-sm px-3.5 py-2.5">
                    {{ $errorMessage }}
                </div>
            @endif

            <form wire:submit="restablecer" class="space-y-4">
                <input type="hidden" wire:model="email">

                <div>
                    <label class="block text-[13px] font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Nueva contraseña</label>
                    <input type="password" wire:model="password" required autofocus
                           class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-900 rounded-lg px-3.5 py-2.5 text-sm outline-none focus:border-brand-blue focus:ring-2 focus:ring-brand-blue-tint dark:focus:ring-0">
                    @error('password') <span class="text-brand-red text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-[13px] font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Confirmar contraseña</label>
                    <input type="password" wire:model="password_confirmation" required
                           class="w-full border border-slate-200 dark:border-slate-700 dark:bg-slate-900 rounded-lg px-3.5 py-2.5 text-sm outline-none focus:border-brand-blue focus:ring-2 focus:ring-brand-blue-tint dark:focus:ring-0">
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="restablecer"
                        class="w-full bg-brand-blue hover:bg-brand-blue-dark text-white rounded-lg py-3 text-[15px] font-semibold transition disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-2.5">
                    <svg wire:loading wire:target="restablecer" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"/>
                        <path class="opacity-90" d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                    <span wire:loading.remove wire:target="restablecer">Restablecer contraseña</span>
                    <span wire:loading wire:target="restablecer">Guardando…</span>
                </button>
            </form>
        </div>
    </div>
</div>
