@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\OrdenTrabajo> $ots */
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
            <div class="t">Órdenes de trabajo</div>
            <div class="muted">Generado: {{ now()->format('d/m/Y H:i') }}</div>
            <div class="muted">{{ $ots->count() }} registro(s)</div>
        </div>
    </div>

    <table class="box">
        <thead>
            <tr>
                <th>N.º</th>
                <th>Cliente</th>
                <th>Descripción</th>
                <th>Tipo</th>
                <th>Prioridad</th>
                <th>Estado</th>
                <th>Creada</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ots as $ot)
                <tr>
                    <td>{{ $ot->numero_ot }}</td>
                    <td>{{ $ot->cliente?->nombre }}</td>
                    <td>{{ $ot->descripcion }}</td>
                    <td>{{ ucfirst($ot->tipo_servicio) }}</td>
                    <td>{{ $ot->prioridad?->nombre }}</td>
                    <td>{{ $ot->estado?->nombre }}</td>
                    <td>{{ $ot->created_at?->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Sin órdenes de trabajo que coincidan con el filtro.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
