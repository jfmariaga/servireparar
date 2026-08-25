@php
    $tabs = [
        ['route' => 'inventario.dashboard', 'label' => 'Dashboard'],
        ['route' => 'inventario.catalogo', 'label' => 'Catálogo'],
        ['route' => 'inventario.movimientos', 'label' => 'Solicitudes'],
        ['route' => 'inventario.auditoria', 'label' => 'Auditoría'],
        ['route' => 'inventario.catalogos', 'label' => 'Categorías y unidades'],
    ];
@endphp

<div class="flex items-center gap-1 mb-6 border-b border-slate-200 dark:border-slate-800 -mt-1">
    @foreach ($tabs as $tab)
        <a href="{{ route($tab['route']) }}"
           class="px-3.5 py-2.5 text-[13.5px] font-semibold border-b-2 -mb-px transition
                  {{ request()->routeIs($tab['route']) ? 'border-brand-blue text-brand-blue' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
