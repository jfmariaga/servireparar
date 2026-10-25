@props(['label'])

{{--
    Botón de acción primaria "Nuevo X" con ícono + texto — convención del
    sistema para encabezados de listado (antes era un x-icon-button de solo
    ícono, poco legible como acción principal de la pantalla).
--}}
<button type="button"
        {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 bg-brand-blue hover:bg-brand-blue-dark text-white text-[13.5px] font-semibold px-4 py-2.5 rounded-lg transition shrink-0']) }}>
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" class="shrink-0"><path d="M12 5v14M5 12h14"/></svg>
    {{ $label }}
</button>
