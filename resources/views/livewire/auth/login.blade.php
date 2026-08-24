<?php

use App\Enums\RolPrioridad;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layout')] class extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;
    public string $errorMessage = '';

    public function login(): void
    {
        $this->errorMessage = '';

        $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            // Mensaje genérico: no revela si el correo existe (spec 001, Acceptance Scenario 2)
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

<div class="min-h-[70vh] flex items-center justify-center">
    <div class="w-full max-w-sm bg-white rounded-lg shadow p-8">
        <h1 class="text-2xl font-bold text-blue-800 mb-1">SERVIOPS</h1>
        <p class="text-sm text-slate-500 mb-6">Iniciar sesión</p>

        @if ($errorMessage)
            <div class="mb-4 rounded bg-red-50 text-red-700 text-sm px-3 py-2">
                {{ $errorMessage }}
            </div>
        @endif

        <form wire:submit="login" class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Email</label>
                <input type="email" wire:model="email" required autofocus
                       class="w-full border rounded px-3 py-2 text-sm">
                @error('email') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Contraseña</label>
                <input type="password" wire:model="password" required
                       class="w-full border rounded px-3 py-2 text-sm">
                @error('password') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="remember">
                Recordarme
            </label>

            <button type="submit"
                    class="w-full bg-blue-800 hover:bg-blue-900 text-white rounded py-2 text-sm font-medium">
                Acceder
            </button>
        </form>
    </div>
</div>
