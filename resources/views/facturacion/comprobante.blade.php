<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $c->tipoNombre() }} {{ $c->numero }} · {{ $cfg['fe_razon_social'] }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body{font-family:'Inter',sans-serif}
        .a4{width:210mm;min-height:148mm}
        @media print {
            .no-print{display:none !important}
            body{background:#fff;margin:0}
            .a4{width:auto;box-shadow:none !important;border:0 !important;margin:0 !important}
            @page{size:A4;margin:12mm}
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen py-8 px-4">

    {{-- Aviso de resultado (envío de correo) --}}
    @if (session('ok'))
        <div class="no-print max-w-3xl mx-auto mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('ok') }}</div>
    @endif
    @if (session('error'))
        <div class="no-print max-w-3xl mx-auto mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="no-print max-w-3xl mx-auto mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    {{-- Acciones (no se imprimen) --}}
    <div class="no-print max-w-3xl mx-auto mb-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('facturacion.index') }}" class="rounded-lg bg-white border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">← Volver</a>
        <button onclick="window.print()" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90" style="background:#059669">Imprimir / Guardar PDF</button>

        <form method="POST" action="{{ route('facturacion.email', $c) }}" class="flex items-center gap-2 ml-auto">
            @csrf
            <input type="email" name="email" required value="{{ $emailCliente }}" placeholder="correo@cliente.com"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                Enviar por correo
            </button>
        </form>
    </div>

    <div class="a4 max-w-3xl mx-auto bg-white rounded-xl shadow-sm border border-slate-200 p-8 text-slate-700">

        {{-- Encabezado: emisor + recuadro del comprobante --}}
        <div class="flex items-start justify-between gap-6 border-b border-slate-200 pb-5">
            <div class="flex items-start gap-3">
                <div class="w-12 h-12 rounded-lg flex items-center justify-center shrink-0" style="background:#059669">
                    <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5l-1.5-1.5L15 21l-1.5-1.5L12 21l-1.5-1.5L9 21l-1.5-1.5L6 21V5a2 2 0 012-2h8a2 2 0 012 2v16z"/></svg>
                </div>
                <div>
                    <h1 class="text-lg font-extrabold text-slate-800 leading-tight">{{ $cfg['fe_razon_social'] }}</h1>
                    @if ($cfg['fe_nombre_comercial'])
                        <p class="text-sm text-slate-500">{{ $cfg['fe_nombre_comercial'] }}</p>
                    @endif
                    <p class="text-xs text-slate-500 mt-1">{{ $cfg['fe_direccion'] }}</p>
                    <p class="text-xs text-slate-500">{{ $cfg['fe_distrito'] }} - {{ $cfg['fe_provincia'] }} - {{ $cfg['fe_departamento'] }}</p>
                </div>
            </div>

            <div class="border-2 border-slate-300 rounded-lg px-5 py-4 text-center min-w-[220px]">
                <p class="text-sm font-bold text-slate-700">R.U.C. {{ $c->ruc_emisor }}</p>
                <p class="mt-1 text-sm font-extrabold uppercase text-emerald-700 tracking-wide">
                    {{ strtoupper($c->tipoNombre()) }} ELECTRÓNICA
                </p>
                <p class="mt-1 text-lg font-mono font-bold text-slate-800">{{ $c->numero }}</p>
            </div>
        </div>

        {{-- Datos del receptor / emisión --}}
        <div class="grid grid-cols-2 gap-x-8 gap-y-1 text-xs py-4 border-b border-slate-200">
            <div class="flex gap-2"><span class="text-slate-400 w-28">Señor(es):</span><span class="font-medium text-slate-700">{{ $c->cliente_nombre ?: 'CLIENTE VARIOS' }}</span></div>
            <div class="flex gap-2"><span class="text-slate-400 w-28">Fecha emisión:</span><span>{{ ($c->enviado_at ?? $c->created_at)->format('d/m/Y') }}</span></div>
            <div class="flex gap-2"><span class="text-slate-400 w-28">{{ $c->cliente_tipo_doc === '6' ? 'RUC:' : 'Documento:' }}</span><span>{{ $c->cliente_num_doc ?: '-' }}</span></div>
            <div class="flex gap-2"><span class="text-slate-400 w-28">Moneda:</span><span>Soles (PEN)</span></div>
            @if ($c->tipo_comprobante === '07' && $c->referencia)
                <div class="flex gap-2 col-span-2"><span class="text-slate-400 w-28">Documento afectado:</span><span class="font-medium">{{ $c->referencia->numero }} — {{ $c->motivo_nota }}</span></div>
            @endif
        </div>

        {{-- Items --}}
        <table class="w-full text-xs my-4">
            <thead>
                <tr class="bg-slate-50 text-slate-500 text-left">
                    <th class="py-2 px-2 font-semibold text-center w-12">Cant.</th>
                    <th class="py-2 px-2 font-semibold text-center w-16">Unidad</th>
                    <th class="py-2 px-2 font-semibold">Descripción</th>
                    <th class="py-2 px-2 font-semibold text-right w-24">P. Unit.</th>
                    <th class="py-2 px-2 font-semibold text-right w-24">Importe</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($c->venta?->detalles ?? [] as $d)
                    <tr>
                        <td class="py-1.5 px-2 text-center">{{ $d->cantidad }}</td>
                        <td class="py-1.5 px-2 text-center">NIU</td>
                        <td class="py-1.5 px-2">{{ $d->descripcion }}</td>
                        <td class="py-1.5 px-2 text-right">{{ number_format($d->precio_unitario, 2) }}</td>
                        <td class="py-1.5 px-2 text-right font-medium">{{ number_format($d->subtotal, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-center text-slate-400">Sin detalle de ítems disponible.</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- Totales --}}
        <div class="flex justify-end">
            <div class="w-64 text-sm space-y-1">
                <div class="flex justify-between text-slate-500"><span>Op. Gravada</span><span>S/ {{ number_format($c->gravado, 2) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>IGV ({{ rtrim(rtrim(number_format($igvPct, 2), '0'), '.') }}%)</span><span>S/ {{ number_format($c->igv, 2) }}</span></div>
                <div class="flex justify-between text-base font-extrabold text-slate-800 border-t border-slate-200 pt-1 mt-1"><span>IMPORTE TOTAL</span><span>S/ {{ number_format($c->total, 2) }}</span></div>
            </div>
        </div>

        <p class="mt-3 text-xs text-slate-600"><span class="text-slate-400">Son:</span> {{ $enLetras }} SOLES</p>

        {{-- Pie: QR + hash + estado --}}
        <div class="mt-6 flex items-end justify-between gap-6 border-t border-dashed border-slate-300 pt-5">
            <div class="flex items-end gap-4">
                <div id="qr" class="shrink-0"></div>
                <div class="text-[10px] text-slate-500 leading-relaxed">
                    <p class="font-semibold text-slate-600">Representación impresa del comprobante electrónico.</p>
                    <p>Consulte su documento en el portal de SUNAT.</p>
                    @if ($c->hash_cpe)
                        <p class="mt-1">Resumen (hash): <span class="font-mono break-all">{{ $c->hash_cpe }}</span></p>
                    @endif
                </div>
            </div>
            <div class="text-right text-[11px]">
                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize {{ $c->estadoClase() }}">{{ $c->estado }}</span>
                @if ($c->sunat_codigo)
                    <p class="text-slate-400 mt-1">Código SUNAT: {{ $c->sunat_codigo }}</p>
                @endif
                <p class="text-slate-400">{{ $cfg['fe_entorno'] === 'produccion' ? 'Producción' : 'Homologación (Beta)' }}</p>
            </div>
        </div>
    </div>

    <script>
        new QRCode(document.getElementById('qr'), {
            text: @json($qr),
            width: 96, height: 96,
            correctLevel: QRCode.CorrectLevel.M
        });
    </script>
</body>
</html>
