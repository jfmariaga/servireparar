@php
    /** @var \App\Models\SolicitudDespacho $solicitud */
    $rem = $solicitud->remision;
    $cli = $solicitud->cliente;
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');

    $logoPath = public_path('img/logo.png');
    $logo = is_file($logoPath)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
        : null;
    $ciudad = config('despachos.sedes.'.$solicitud->sede);

    // Todas las líneas se listan juntas para el cliente: qué se le entregó.
    // La distinción inventario / compra externa es trazabilidad interna y no
    // aparece en este documento.
    $lineas = $solicitud->detalles->map(fn ($d) => [
        'cantidad' => $d->cantidad,
        'referencia' => $d->origen === 'inventario' ? ($d->inventario->nombre ?? $d->descripcion) : $d->descripcion,
        'codigo' => $d->origen === 'inventario' ? $d->inventario->codigo : null,
    ]);
@endphp
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
        .items th.ref, .items td.ref { width: 190px; }
        .note { margin-top: 10px; border: 1px solid #cbd5e1; padding: 8px; min-height: 40px; }
        .sign-row { margin-top: 34px; width: 100%; }
        .sign-row td { width: 50%; vertical-align: bottom; padding-top: 6px; }
        .sign-row .line { border-top: 1px solid #0f172a; padding-top: 4px; }
        .sign img { max-height: 70px; max-width: 240px; }
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
            <div class="t">REMISIÓN {{ $solicitud->sede }}</div>
            @if ($ciudad)<div class="muted">{{ $ciudad }}</div>@endif
            <div class="n">N° {{ $rem->numero }}</div>
            <div class="muted">Fecha: {{ optional($rem->entregada_en ?? $rem->fecha)->format('d/m/Y') }}</div>
        </div>
    </div>

    <table class="cli">
        <tr>
            <td class="k">Cliente:</td><td><strong>{{ $cli->nombre }}</strong></td>
            <td class="k">NIT:</td><td>{{ $cli->nit ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">Dirección:</td><td>{{ $cli->direccion ?: '—' }}</td>
            <td class="k">Teléfono:</td><td>{{ $cli->telefono ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">E-mail:</td><td>{{ $cli->correo ?: '—' }}</td>
            <td class="k">Remisión de:</td><td>{{ $solicitud->numero }}</td>
        </tr>
    </table>

    <p style="margin:12px 0 0; font-weight:bold;">Despachamos a ustedes los siguientes artículos:</p>

    <table class="box items">
        <thead>
            <tr><th class="num">Cant.</th><th class="ref">Referencia</th><th>Descripción</th></tr>
        </thead>
        <tbody>
        @forelse ($lineas as $l)
            <tr>
                <td class="num">{{ $qty($l['cantidad']) }}</td>
                <td class="ref">{{ $l['referencia'] }}</td>
                <td>{{ $l['codigo'] ? 'Cód. '.$l['codigo'] : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">Sin artículos.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="note">
        {{ $rem->nota_entrega ?: '' }}
    </div>

    <table class="sign-row">
        <tr>
            <td>
                @if ($rem->firma_entrega)
                    <div class="sign"><img src="{{ $rem->firma_entrega }}" alt="Firma de quien entrega"></div>
                @endif
                <div class="line">
                    <strong>Entrega:</strong> {{ $rem->entregado_por_nombre ?? optional($rem->generadaPor)->name ?? '—' }}
                </div>
            </td>
            <td>
                @if ($rem->firma)
                    <div class="sign" style="margin-left:20px;"><img src="{{ $rem->firma }}" alt="Firma de quien recibe"></div>
                @endif
                <div class="line" style="margin-left:20px;">
                    <strong>Recibe:</strong> {{ $rem->recibido_por_nombre ?? '—' }}<br>
                    C.C. {{ $rem->recibido_por_documento ?? '—' }}
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
