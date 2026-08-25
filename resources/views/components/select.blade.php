@props(['placeholder' => 'Seleccionar...', 'resetKey' => null])

{{--
    Envoltorio con Tom Select para toda lista desplegable de la app (equivalente
    a "select2" sin depender de jQuery). wire:ignore evita que Livewire reemplace
    el DOM que Tom Select ya transformó; cuando el valor puede cambiar por código
    (por ejemplo al abrir un formulario de edición) pasa `reset-key` con algo que
    cambie en ese caso (ej. el id que se está editando) para forzar el remontaje.
--}}
<div wire:ignore @if ($resetKey) wire:key="{{ $resetKey }}" @endif x-data x-init="window.ServiopsSelect.mount($el.querySelector('select'))">
    <select {{ $attributes }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        {{ $slot }}
    </select>
</div>
