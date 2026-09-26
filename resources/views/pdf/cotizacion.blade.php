<?php
/** @var \App\Models\Cotizacion $cotizacion */
$cli = $cotizacion->cliente;
$qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');

$logoPath = public_path('img/logo.png');
$logo = is_file($logoPath)
    ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
    : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 12px; color: #0f172a; margin: 0; }
        .head { display: flex; justify-content: space-between; border-bottom: 3px solid #12172b; padding-bottom: 10px; margin-bottom: 6px; }
        .brand img { height: 40px; width: auto; }
        .doc { text-align: right; }
        .doc .t { font-size: 14px; font-weight: bold; }
        .doc .n { font-size: 16px; font-weight: bold; color: #e0332c; }
        .muted { color: #64748b; }
        table.box { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.box th, table.box td { text-align: left; padding: 5px 8px; border: 1px solid #cbd5e1; vertical-align: top; }
        table.box th { font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #475569; background: #f1f5f9; }
        .cli td { padding: 3px 0; }
        .cli .k { color: #64748b; width: 90px; }
        .items th.num, .items td.num { text-align: center; width: 50px; }
        .items th.money, .items td.money { text-align: right; width: 110px; }
        .total-row td { font-weight: bold; }
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
            <div class="t">COTIZACIÓN</div>
            <div class="n">N° {{ $cotizacion->numero }}</div>
            <div class="muted">Fecha: {{ $cotizacion->created_at->format('d/m/Y') }}</div>
        </div>
    </div>

    <table class="cli">
        <tr>
            <td class="k">Cliente:</td><td><strong>{{ $cli->nombre ?? 'Por identificar' }}</strong></td>
            <td class="k">NIT:</td><td>{{ $cli->nit ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">Dirección:</td><td>{{ $cli->direccion ?? '—' }}</td>
            <td class="k">Teléfono:</td><td>{{ $cli->telefono ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">E-mail:</td><td colspan="3">{{ $cli->correo ?? '—' }}</td>
        </tr>
    </table>

    <table class="box items">
        <thead>
            <tr><th>Ítem</th><th class="num">Cant.</th><th class="money">Costo unit.</th><th class="money">Total</th></tr>
        </thead>
        <tbody>
        @forelse ($cotizacion->detalles as $d)
            <tr>
                <td>{{ $d->nombreItem() }}</td>
                <td class="num">{{ $qty($d->cantidad) }}</td>
                <td class="money">{{ \App\Support\Moneda::cop($d->costo_unitario) }}</td>
                <td class="money">{{ \App\Support\Moneda::cop($d->valor_total) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">Sin ítems.</td></tr>
        @endforelse
        <tr class="total-row">
            <td colspan="3" style="text-align:right;">Total</td>
            <td class="money">{{ \App\Support\Moneda::cop($cotizacion->total) }}</td>
        </tr>
        </tbody>
    </table>
</body>
</html>
