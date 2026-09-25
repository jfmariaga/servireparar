@props(['items' => []])

{{--
    Miga de pan reutilizable: siempre parte de "Inicio" y agrega los pasos que
    pasa el caller. Cada paso es ['label' => ..., 'route' => ..., 'params' => []]
    o ['label' => ..., 'url' => ...]; el último paso (o cualquiera sin
    route/url) se muestra como texto plano, no como enlace — es la página
    actual, no un destino de navegación.
--}}
<nav aria-label="Miga de pan" class="flex items-center gap-1.5 text-[12.5px] text-slate-400 dark:text-slate-500 mb-4 flex-wrap">
    <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-brand-blue dark:hover:text-brand-blue-tint flex items-center gap-1 shrink-0">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11l8-7 8 7v9a1 1 0 01-1 1h-4v-6H9v6H5a1 1 0 01-1-1v-9z"/></svg>
        <span>Inicio</span>
    </a>

    @foreach ($items as $item)
        <span class="text-slate-300 dark:text-slate-700">/</span>
        @php $href = $item['url'] ?? (isset($item['route']) ? route($item['route'], $item['params'] ?? []) : null); @endphp
        @if ($href && ! $loop->last)
            <a href="{{ $href }}" wire:navigate class="hover:text-brand-blue dark:hover:text-brand-blue-tint truncate max-w-[14rem]">{{ $item['label'] }}</a>
        @else
            <span class="text-slate-600 dark:text-slate-300 font-medium truncate max-w-[16rem]">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
