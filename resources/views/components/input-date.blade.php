@props(['resetKey' => null])

{{--
    Envoltorio con Flatpickr para todo campo de fecha de la app (mismo patrón
    que x-select con Tom Select). wire:ignore evita que Livewire reemplace el
    DOM que Flatpickr ya transformó; cuando el valor puede cambiar por código
    (por ejemplo al abrir un formulario de edición) pasa `reset-key` con algo
    que cambie en ese caso (ej. el id que se está editando) para forzar el
    remontaje.
--}}
<div wire:ignore @if ($resetKey) wire:key="{{ $resetKey }}" @endif x-data x-init="window.ServiopsDatepicker.mount($el.querySelector('input'))">
    <div class="relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        <input type="text" autocomplete="off" placeholder="dd/mm/aaaa" {{ $attributes }}>
    </div>
</div>
