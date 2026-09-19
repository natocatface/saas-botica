<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $c->tipoNombre() }} {{ $c->numero }}</title>
    <style>
        * { font-family: DejaVu Sans, Arial, sans-serif; }
        body { color: #334155; font-size: 12px; margin: 0; }
        .muted { color: #94a3b8; }
        .box { border: 1.5px solid #cbd5e1; border-radius: 6px; padding: 10px 14px; text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        .items th { background: #f1f5f9; color: #475569; text-align: left; padding: 6px 8px; font-size: 11px; }
        .items td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; }
        .right { text-align: right; }
        .center { text-align: center; }
        .totales td { padding: 4px 8px; }
        h1 { font-size: 16px; margin: 0; color: #0f172a; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; background: #ecfdf5; color: #047857; font-weight: bold; font-size: 11px; text-transform: capitalize; }
    </style>
</head>
<body>
    {{-- Encabezado --}}
    <table>
        <tr>
            <td style="width:62%; vertical-align:top;">
                <h1>{{ $cfg['fe_razon_social'] }}</h1>
                @if ($cfg['fe_nombre_comercial'])
                    <div class="muted">{{ $cfg['fe_nombre_comercial'] }}</div>
                @endif
                <div class="muted" style="margin-top:4px;">{{ $cfg['fe_direccion'] }}</div>
                <div class="muted">{{ $cfg['fe_distrito'] }} - {{ $cfg['fe_provincia'] }} - {{ $cfg['fe_departamento'] }}</div>
            </td>
            <td style="width:38%; vertical-align:top;">
                <div class="box">
                    <div style="font-weight:bold;">R.U.C. {{ $c->ruc_emisor }}</div>
                    <div style="font-weight:bold; color:#047857; margin:4px 0;">{{ strtoupper($c->tipoNombre()) }} ELECTRÓNICA</div>
                    <div style="font-size:15px; font-weight:bold;">{{ $c->numero }}</div>
                </div>
            </td>
        </tr>
    </table>

    <hr style="border:none;border-top:1px solid #e2e8f0;margin:12px 0;">

    {{-- Receptor --}}
    <table style="font-size:11px;">
        <tr>
            <td style="width:50%;"><span class="muted">Señor(es):</span> <strong>{{ $c->cliente_nombre ?: 'CLIENTE VARIOS' }}</strong></td>
            <td style="width:50%;"><span class="muted">Fecha emisión:</span> {{ ($c->enviado_at ?? $c->created_at)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><span class="muted">{{ $c->cliente_tipo_doc === '6' ? 'RUC:' : 'Documento:' }}</span> {{ $c->cliente_num_doc ?: '-' }}</td>
            <td><span class="muted">Moneda:</span> Soles (PEN)</td>
        </tr>
        @if ($c->tipo_comprobante === '07' && $c->referencia)
            <tr>
                <td colspan="2"><span class="muted">Documento afectado:</span> <strong>{{ $c->referencia->numero }}</strong> — {{ $c->motivo_nota }}</td>
            </tr>
        @endif
    </table>

    {{-- Ítems --}}
    <table class="items" style="margin-top:12px;">
        <thead>
            <tr>
                <th class="center" style="width:36px;">Cant.</th>
                <th class="center" style="width:52px;">Unidad</th>
                <th>Descripción</th>
                <th class="right" style="width:80px;">P. Unit.</th>
                <th class="right" style="width:80px;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($c->venta?->detalles ?? [] as $d)
                <tr>
                    <td class="center">{{ $d->cantidad }}</td>
                    <td class="center">NIU</td>
                    <td>{{ $d->descripcion }}</td>
                    <td class="right">{{ number_format($d->precio_unitario, 2) }}</td>
                    <td class="right">{{ number_format($d->subtotal, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="center muted" style="padding:14px;">Sin detalle de ítems disponible.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Totales --}}
    <table style="margin-top:12px;">
        <tr>
            <td style="width:60%; vertical-align:top; font-size:11px;">
                <span class="muted">Son:</span> {{ $enLetras }} SOLES
                @if ($c->hash_cpe)
                    <div style="margin-top:10px;" class="muted">Resumen (hash): {{ $c->hash_cpe }}</div>
                @endif
                <div style="margin-top:8px;">
                    Estado SUNAT: <span class="badge">{{ $c->estado }}</span>
                    @if ($c->sunat_codigo) <span class="muted">· código {{ $c->sunat_codigo }}</span>@endif
                </div>
            </td>
            <td style="width:40%; vertical-align:top;">
                <table class="totales">
                    <tr><td class="muted">Op. Gravada</td><td class="right">S/ {{ number_format($c->gravado, 2) }}</td></tr>
                    <tr><td class="muted">IGV ({{ rtrim(rtrim(number_format($igvPct, 2), '0'), '.') }}%)</td><td class="right">S/ {{ number_format($c->igv, 2) }}</td></tr>
                    <tr><td style="font-weight:bold; font-size:14px; border-top:1px solid #cbd5e1;">TOTAL</td><td class="right" style="font-weight:bold; font-size:14px; border-top:1px solid #cbd5e1;">S/ {{ number_format($c->total, 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <p class="muted" style="margin-top:18px; font-size:10px;">
        Representación impresa del comprobante electrónico · {{ $cfg['fe_entorno'] === 'produccion' ? 'Producción' : 'Homologación (Beta)' }}.
        El documento con validez tributaria es el archivo XML.
    </p>
</body>
</html>
