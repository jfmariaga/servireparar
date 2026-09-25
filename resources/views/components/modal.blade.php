@props(['show' => false, 'title' => null, 'onClose' => 'cerrarModal', 'maxWidth' => 'max-w-lg'])

{{--
    Modal genérico controlado por el servidor (no por estado Alpine local):
    `show` es una expresión Livewire ($modal === 'ot', etc.), por lo que abrir
    y cerrar siempre pasa por un wire:click/wire:action — igual que el resto
    de la app, sin sincronización manual Alpine↔Livewire.
--}}
@if ($show)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="$wire.{{ $onClose }}()">
        <div class="absolute inset-0 bg-black/40" wire:click="{{ $onClose }}"></div>

        <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl w-full {{ $maxWidth }} max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-slate-100 dark:border-slate-800 shrink-0">
                <h3 class="font-bold text-sm">{{ $title }}</h3>
                <button type="button" wire:click="{{ $onClose }}"
                        class="shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="px-5 py-4 overflow-y-auto">
                {{ $slot }}
            </div>
        </div>
    </div>
@endif
