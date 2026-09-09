@php
    $items = [
        [
            'route' => 'dashboard',
            'label' => 'Inicio',
            'ability' => null,
            'icon' => 'M4 11l8-7 8 7v9a1 1 0 01-1 1h-4v-6H9v6H5a1 1 0 01-1-1v-9z',
        ],
        [
            'route' => 'clientes.index',
            'label' => 'Clientes',
            'ability' => 'manage-clientes',
            'icon' => 'M5 20c0-3.9 3.1-6.5 7-6.5s7 2.6 7 6.5|M12 13a3.5 3.5 0 100-7 3.5 3.5 0 000 7z',
        ],
        [
            'route' => 'proveedores.index',
            'label' => 'Proveedores',
            'ability' => 'manage-proveedores',
            'icon' => 'M3.5 7.5L12 3l8.5 4.5V16L12 20.5 3.5 16z|M3.5 7.5L12 12l8.5-4.5M12 12v8.5',
        ],
        [
            'route' => 'contratistas.index',
            'label' => 'Contratistas',
            'ability' => 'manage-contratistas',
            'icon' => 'M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z',
        ],
        [
            'route' => 'ordenes-trabajo.tablero',
            'activePattern' => 'ordenes-trabajo.*',
            'label' => 'Órdenes de trabajo',
            'ability' => 'manage-ot',
            'icon' => 'M8 4h8a1 1 0 011 1v15l-5-3-5 3V5a1 1 0 011-1z|M9 9h6|M9 13h4',
        ],
        [
            'route' => 'equipos.index',
            'label' => 'Equipos',
            'ability' => 'manage-equipos',
            'icon' => 'M4 5h16a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1z|M9 21h6|M12 17v4',
        ],
        [
            'route' => 'inventario.dashboard',
            'activePattern' => 'inventario.*',
            'label' => 'Inventario',
            'ability' => 'manage-inventario',
            'icon' => 'M4 4.5h16v4.5H4z|M4 10.5h16v4.5H4z|M4 16.5h16v3H4z',
        ],
        [
            'route' => 'insumos-ot',
            'activePattern' => 'insumos-ot',
            'label' => 'Insumos para OT',
            'ability' => 'manage-inventario',
            'icon' => 'M20 7L9 18l-5-5|M13 5h7v7',
        ],
        [
            'route' => 'despachos.index',
            'activePattern' => 'despachos.*',
            'label' => 'Despachos',
            'ability' => 'manage-despachos',
            'icon' => 'M3 7h11v8H3z|M14 10h4l3 3v2h-7z|M7.5 17.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z|M17.5 17.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z',
        ],
        [
            'route' => 'usuarios.index',
            'label' => 'Usuarios',
            'ability' => 'manage-usuarios',
            'icon' => 'M9 8a3.2 3.2 0 110 6.4A3.2 3.2 0 019 8z|M3.5 20c0-3.3 2.5-5.5 5.5-5.5s5.5 2.2 5.5 5.5|M17.5 8.5a2.4 2.4 0 110 4.8|M15.7 14.8c2.3.4 3.8 2.2 3.8 5.2',
        ],
    ];

    $user = auth()->user();

    $despachosPendientes = $user->hasAnyRole(['Almacenista', 'Administrador'])
        ? \App\Models\SolicitudDespacho::whereIn('estado', ['solicitada', 'recibida', 'remisionada'])->count()
        : 0;

    // Contadores de acción pendiente por rol (Phase 11 / D5).
    $insumosPendientes = $user->can('manage-inventario')
        ? \App\Models\SolicitudInsumoOt::where('estado', 'pendiente')->count()
        : 0;

    $otPendientes = 0;
    if ($user->can('manage-ot')) {
        $otPendientes += \App\Models\OrdenTrabajo::whereHas('estado', fn ($q) => $q->where('slug', 'en_revision'))->count();
        if ($user->hasRole('Administrador')) {
            $otPendientes += \App\Models\OrdenTrabajo::where('salida_estado', 'solicitada')->count();
        }
    }

    $badges = [
        'despachos.index' => $despachosPendientes,
        'insumos-ot' => $insumosPendientes,
        'ordenes-trabajo.tablero' => $otPendientes,
    ];
@endphp

@foreach ($items as $item)
    @continue($item['ability'] && ! auth()->user()->can($item['ability']))
    @php
        $isActive = request()->routeIs($item['activePattern'] ?? $item['route']);
        $badge = ($badges[$item['route']] ?? 0) > 0 ? $badges[$item['route']] : null;
    @endphp

    @if ($variant === 'sidebar')
        <a href="{{ route($item['route']) }}"
           @click="$store.ui.mobileNavOpen = false"
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-[13.5px] font-medium transition
                  {{ $isActive ? 'bg-brand-navy-active text-white' : 'text-slate-400 hover:bg-brand-navy-hover hover:text-white' }}">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
                @foreach (explode('|', $item['icon']) as $path)
                    <path d="{{ $path }}" />
                @endforeach
            </svg>
            {{ $item['label'] }}
            @if ($badge)
                <span class="ml-auto bg-brand-red text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center">{{ $badge }}</span>
            @endif
        </a>
    @else
        <a href="{{ route($item['route']) }}"
           class="flex items-center gap-2 px-3 py-2 rounded-lg text-[13px] font-medium transition shrink-0 whitespace-nowrap
                  {{ $isActive ? 'bg-brand-blue-tint text-brand-blue dark:bg-brand-navy-active dark:text-white' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
                @foreach (explode('|', $item['icon']) as $path)
                    <path d="{{ $path }}" />
                @endforeach
            </svg>
            {{ $item['label'] }}
            @if ($badge)
                <span class="bg-brand-red text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center">{{ $badge }}</span>
            @endif
        </a>
    @endif
@endforeach
