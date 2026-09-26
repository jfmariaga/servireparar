<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layout', ['title' => 'Notificaciones'])] class extends Component
{
    use WithPagination;

    public bool $soloNoLeidas = false;

    public function with(): array
    {
        $query = auth()->user()->notifications()->latest();

        if ($this->soloNoLeidas) {
            $query->whereNull('read_at');
        }

        return [
            'notificaciones' => $query->paginate(20),
        ];
    }

    public function marcarLeida(string $id): void
    {
        auth()->user()->notifications()->find($id)?->markAsRead();
    }

    public function marcarTodasLeidas(): void
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

<div>
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-lg font-bold text-slate-800 dark:text-slate-100">Notificaciones</h1>
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                <input type="checkbox" wire:model.live="soloNoLeidas" class="rounded border-slate-300">
                Solo no leídas
            </label>
            <button wire:click="marcarTodasLeidas" class="text-xs font-semibold text-brand-blue hover:underline">
                Marcar todas leídas
            </button>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
        @forelse ($notificaciones as $n)
            <div wire:key="{{ $n->id }}"
                 class="flex items-start gap-3 px-4 py-3 border-b border-slate-100 dark:border-slate-800 last:border-0
                        {{ $n->read_at ? '' : 'bg-brand-blue-tint/40 dark:bg-brand-navy-active/30' }}">
                <span class="mt-1.5 w-1.5 h-1.5 rounded-full shrink-0 {{ $n->read_at ? 'bg-transparent' : ($n->data['icono'] === 'alerta' ? 'bg-brand-red' : 'bg-brand-blue') }}"></span>
                <div class="min-w-0 flex-1">
                    <p class="text-[13px] font-semibold text-slate-800 dark:text-slate-100">{{ $n->data['titulo'] ?? 'Aviso' }}</p>
                    <p class="text-[12.5px] text-slate-500 dark:text-slate-400 leading-snug">{{ $n->data['cuerpo'] ?? '' }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">{{ $n->created_at?->diffForHumans() }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @if ($n->data['url'] ?? null)
                        <button wire:click="abrir('{{ $n->id }}')" class="text-[11px] font-semibold text-brand-blue hover:underline">Ver</button>
                    @endif
                    @unless ($n->read_at)
                        <button wire:click="marcarLeida('{{ $n->id }}')" class="text-[11px] font-semibold text-slate-400 hover:underline">Marcar leída</button>
                    @endunless
                </div>
            </div>
        @empty
            <p class="px-4 py-10 text-center text-sm text-slate-400">Sin notificaciones.</p>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $notificaciones->links() }}
    </div>
</div>
