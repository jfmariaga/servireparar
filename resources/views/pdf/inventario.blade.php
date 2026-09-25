@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Inventario> $items */
    $logoPath = public_path('img/logo.png');
    $logo = is_file($logoPath)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
        : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #0f172a; margin: 0; }
        .head { display: flex; justify-content: space-between; border-bottom: 3px solid #12172b; padding-bottom: 10px; margin-bottom: 10px; }
        .brand img { height: 36px; width: auto; }
        .doc { text-align: right; }
        .doc .t { font-size: 14px; font-weight: bold; }
        .muted { color: #64748b; }
        table.box { width: 100%; border-collapse: collapse; }
        table.box th, table.box td { text-align: left; padding: 5px 7px; border: 1px solid #cbd5e1; vertical-align: top; }
        table.box th { font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #475569; background: #f1f5f9; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <div class="head">
        <div class="brand">
            @if ($logo)
                <img src="{{ $logo }}" alt="SERVIREPARAR">
            @endif
        </div>
        <div class="doc">
            <div class="t">Inventario</div>
            <div class="muted">Generado: {{ now()->format('d/m/Y H:i') }}</div>
            <div class="muted">{{ $items->count() }} ítem(s)</div>
        </div>
    </div>

    <table class="box">
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Categoría</th>
                <th class="num">Stock actual</th>
                <th class="num">Stock mínimo</th>
                <th>Unidad</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item->codigo }}</td>
                    <td>{{ $item->nombre }}</td>
                    <td>{{ ucfirst($item->tipo) }}</td>
                    <td>{{ $item->categoria?->nombre }}</td>
                    <td class="num">{{ $item->stock_actual }}</td>
                    <td class="num">{{ $item->stock_minimo }}</td>
                    <td>{{ $item->unidadMedida?->nombre }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Sin ítems que coincidan con el filtro.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
