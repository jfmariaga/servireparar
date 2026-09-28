@php
    $items = [
        [
            'route' => 'dashboard',
            'label' => 'Inicio',
            'ability' => null,
            'icon' => 'M4 11l8-7 8 7v9a1 1 0 01-1 1h-4v-6H9v6H5a1 1 0 01-1-1v-9z',
        ],
        // --- Comercial: clientes y cotizaciones ---
        [
            'route' => 'clientes.index',
            'label' => 'Clientes',
            'ability' => 'manage-clientes',
            'icon' => 'M5 20c0-3.9 3.1-6.5 7-6.5s7 2.6 7 6.5|M12 13a3.5 3.5 0 100-7 3.5 3.5 0 000 7z',
        ],
        [
            'route' => 'cotizaciones.tablero',
            'activePattern' => 'cotizaciones.*',
            'label' => 'Cotizaciones',
            'ability' => 'manage-cotizaciones',
            // En pulido (spec 006): solo visible en desarrollo hasta cerrar los flujos pendientes.
            'soloDesarrollo' => true,
            'icon' => 'M6 4h9l3 3v13a1 1 0 01-1 1H6a1 1 0 01-1-1V5a1 1 0 011-1z|M9 9h6|M9 13h6|M9 17h3',
        ],
        // --- Taller: órdenes de trabajo y equipos de clientes ---
        [
            'route' => 'ordenes-trabajo.tablero',
            'activePattern' => 'ordenes-trabajo.*',
            'label' => 'Órdenes de trabajo',
            // Admin/Jefe gestionan todas; el Técnico también entra pero solo ve las suyas
            // (OrdenTrabajo::scopeVisiblesPara) — antes solo se llegaba ahí desde el enlace
            // "← Tablero" del detalle, sin ítem fijo en el menú para el Técnico.
            'ability' => ['manage-ot', 'execute-ot'],
            'icon' => 'M8 4h8a1 1 0 011 1v15l-5-3-5 3V5a1 1 0 011-1z|M9 9h6|M9 13h4',
        ],
        [
            'route' => 'equipos.index',
            'label' => 'Equipos',
            'ability' => 'manage-equipos',
            'icon' => 'M4 5h16a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1z|M9 21h6|M12 17v4',
        ],
        // --- Bodega: inventario, insumos/herramienta de OT, compras y despachos ---
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
            'route' => 'prestamos-herramienta',
            'activePattern' => 'prestamos-herramienta',
            'label' => 'Préstamos de herramienta',
            'ability' => 'attend-ot-insumo',
            'icon' => 'M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z',
        ],
        [
            'route' => 'compras.tablero',
            'activePattern' => 'compras.*',
            'label' => 'Compras',
            'ability' => 'manage-compras',
            // En pulido (spec 006): solo visible en desarrollo hasta cerrar los flujos pendientes.
            'soloDesarrollo' => true,
            'icon' => 'M4 7h16l-1.5 10.5a1.5 1.5 0 01-1.5 1.5H7a1.5 1.5 0 01-1.5-1.5L4 7z|M8 7V5a4 4 0 018 0v2',
        ],
        [
            'route' => 'despachos.index',
            'activePattern' => 'despachos.*',
            'label' => 'Despachos',
            'ability' => 'manage-despachos',
            // En pulido (spec 003/006): solo visible en desarrollo hasta cerrar los flujos pendientes.
            'soloDesarrollo' => true,
            'icon' => 'M3 7h11v8H3z|M14 10h4l3 3v2h-7z|M7.5 17.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z|M17.5 17.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z',
        ],
        // --- Terceros: proveedores y contratistas ---
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
        // --- Administración ---
        [
            'route' => 'usuarios.index',
            'label' => 'Usuarios',
            'ability' => 'manage-usuarios',
            'icon' => 'M9 8a3.2 3.2 0 110 6.4A3.2 3.2 0 019 8z|M3.5 20c0-3.3 2.5-5.5 5.5-5.5s5.5 2.2 5.5 5.5|M17.5 8.5a2.4 2.4 0 110 4.8|M15.7 14.8c2.3.4 3.8 2.2 3.8 5.2',
        ],
        [
            'route' => 'reportes.auditoria',
            'label' => 'Auditoría',
            'ability' => 'view-auditoria',
            'icon' => 'M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z|M9.5 12l2 2 3.5-3.5',
        ],
        [
            'route' => 'configuraciones.index',
            'label' => 'Configuración',
            'ability' => 'manage-configuraciones',
            'icon' => 'M12 15a3 3 0 100-6 3 3 0 000 6z|M19.4 13a7.4 7.4 0 000-2l1.9-1.5-2-3.4-2.2.9a7.3 7.3 0 00-1.7-1l-.3-2.4h-4l-.3 2.4a7.3 7.3 0 00-1.7 1l-2.2-.9-2 3.4L4.6 11a7.4 7.4 0 000 2l-1.9 1.5 2 3.4 2.2-.9c.5.4 1.1.7 1.7 1l.3 2.4h4l.3-2.4c.6-.3 1.2-.6 1.7-1l2.2.9 2-3.4z',
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

    $prestamosPendientes = $user->can('attend-ot-insumo')
        ? \App\Models\PrestamoHerramienta::where('estado', 'solicitada')->count()
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
        'prestamos-herramienta' => $prestamosPendientes,
        'ordenes-trabajo.tablero' => $otPendientes,
    ];
@endphp

@foreach ($items as $item)
    @php
        $puedeVer = match (true) {
            $item['ability'] === null => true,
            is_array($item['ability']) => auth()->user()->canAny($item['ability']),
            default => auth()->user()->can($item['ability']),
        };
    @endphp
    @continue(! $puedeVer)
    @continue(($item['soloDesarrollo'] ?? false) && app()->isProduction())
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
