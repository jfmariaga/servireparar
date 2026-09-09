<?php

use Livewire\Volt\Component;

new class extends Component
{
    public function with(): array
    {
        $user = auth()->user();

        return [
            'noLeidas' => $user->unreadNotifications()->count(),
            'ultimas' => $user->notifications()->latest()->limit(15)->get(),
            'poll' => (int) config('ot.notif_poll_segundos', 45),
        ];
    }

    public function marcarLeidas(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function abrir(string $id): void
    {
        $n = auth()->user()->notifications()->find($id);

        if (! $n) {
            return;
        }

        $n->markAsRead();

        if ($url = $n->data['url'] ?? null) {
            $this->redirect($url, navigate: false);
        }
    }
}; ?>

<div wire:poll.{{ $poll }}s x-data="{ open: false }" class="relative">
    <button @click="open = !open" @click.outside="open = false"
            class="relative w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
            title="Notificaciones">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/>
        </svg>
        @if ($noLeidas > 0)
            <span class="absolute -top-1.5 -right-1.5 bg-brand-red text-white text-[9px] font-bold rounded-full min-w-[16px] h-[16px] px-1 flex items-center justify-center">{{ $noLeidas > 9 ? '9+' : $noLeidas }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak @click.stop
         class="absolute right-0 mt-2 w-80 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg z-40 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-slate-100 dark:border-slate-800">
            <span class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Notificaciones</span>
            @if ($noLeidas > 0)
                <button wire:click="marcarLeidas" class="text-[11px] font-semibold text-brand-blue hover:underline">Marcar leídas</button>
            @endif
        </div>
        <div class="max-h-96 overflow-y-auto">
            @forelse ($ultimas as $n)
                <button wire:click="abrir('{{ $n->id }}')"
                        class="w-full text-left px-4 py-2.5 border-b border-slate-50 dark:border-slate-800/60 last:border-0 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition
                               {{ $n->read_at ? '' : 'bg-brand-blue-tint/40 dark:bg-brand-navy-active/30' }}">
                    <div class="flex items-start gap-2">
                        <span class="mt-1 w-1.5 h-1.5 rounded-full shrink-0 {{ $n->read_at ? 'bg-transparent' : ($n->data['icono'] === 'alerta' ? 'bg-brand-red' : 'bg-brand-blue') }}"></span>
                        <div class="min-w-0">
                            <p class="text-[12.5px] font-semibold text-slate-800 dark:text-slate-100">{{ $n->data['titulo'] ?? 'Aviso' }}</p>
                            <p class="text-[11.5px] text-slate-500 dark:text-slate-400 leading-snug">{{ $n->data['cuerpo'] ?? '' }}</p>
                            <p class="text-[10.5px] text-slate-400 mt-0.5">{{ $n->created_at?->diffForHumans() }}</p>
                        </div>
                    </div>
                </button>
            @empty
                <p class="px-4 py-6 text-center text-xs text-slate-400">Sin notificaciones.</p>
            @endforelse
        </div>
    </div>
</div>
