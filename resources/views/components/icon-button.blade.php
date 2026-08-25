@props(['title', 'variant' => 'default'])

@php
    $base = 'inline-flex items-center justify-center w-9 h-9 rounded-lg transition shrink-0 disabled:opacity-50 disabled:cursor-not-allowed';

    $variants = [
        'default' => 'border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800',
        'primary' => 'bg-brand-blue hover:bg-brand-blue-dark text-white',
        'danger' => 'border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:text-brand-red hover:border-brand-red-tint dark:hover:border-brand-red',
        'success' => 'border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:text-emerald-600 hover:border-emerald-200 dark:hover:border-emerald-800',
    ];
@endphp

{{--
    Botón de acción de solo ícono — convención del sistema para acciones de fila y de cabecera
    (nuevo / editar / activar / inactivar) en todos los módulos. Siempre incluye "title" y un
    texto accesible oculto (sr-only) para lectores de pantalla, ya que no lleva texto visible.
--}}
<button type="button"
        {{ $attributes->merge(['title' => $title, 'class' => $base.' '.($variants[$variant] ?? $variants['default'])]) }}>
    {{ $slot }}
    <span class="sr-only">{{ $title }}</span>
</button>
