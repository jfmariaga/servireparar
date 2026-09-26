@props(['size' => 'md'])

{{--
    Input de archivo estándar de la app: mismo acento de marca (brand-blue) que
    <x-input> y <x-select>, en vez del botón gris nativo del navegador. Acepta
    cualquier atributo nativo (accept, capture, multiple) y wire:model.
    `size="sm"` es para filas compactas (ej. una tarea en una lista); el resto
    de la app usa el tamaño por defecto.
--}}
@php
    $tamaños = [
        'md' => 'text-sm file:mr-3 file:px-3.5 file:py-2.5 file:text-[13px]',
        'sm' => 'text-[11px] file:mr-2 file:px-2.5 file:py-1 file:text-[11px]',
    ];
@endphp
<input {{ $attributes->merge([
    'type' => 'file',
    'class' => 'text-slate-500 dark:text-slate-400 file:cursor-pointer file:rounded-lg file:border-0 file:bg-brand-blue-tint file:font-semibold file:text-brand-blue hover:file:bg-brand-blue/15 dark:file:bg-brand-navy-active dark:file:text-white cursor-pointer '.$tamaños[$size],
]) }}>
