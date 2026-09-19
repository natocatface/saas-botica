@extends('layouts.app')
@section('titulo', 'Facturación Electrónica')

@section('contenido')
<div class="max-w-5xl mx-auto space-y-6">

    {{-- ===================== ENCABEZADO ===================== --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-600 to-brand-800 p-6 text-white shadow-sm">
        <div class="absolute -right-8 -top-10 h-40 w-40 rounded-full bg-white/10"></div>
        <div class="absolute right-16 bottom-0 h-24 w-24 rounded-full bg-white/5"></div>

        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-4">
                <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-white/15">
                    <x-icon name="receipt" />
                </span>
                <div>
                    <h1 class="text-xl font-bold flex items-center gap-2">
                        Facturación Electrónica
                        <span class="text-lg">🇵🇪</span>
                        <span class="text-sm font-medium text-white/80">Perú</span>
                    </h1>
                    <p class="mt-1 max-w-xl text-sm text-white/80">
                        Emisión de comprobantes electrónicos ante <strong>SUNAT</strong> · UBL 2.1 ·
                        boletas, facturas y notas de crédito.
                    </p>
                </div>
            </div>

            <div class="text-right">
                <span class="inline-block rounded-lg bg-white/15 px-3 py-1 text-sm font-bold tracking-wide">SUNAT</span>
                <p class="mt-1 text-[11px] text-white/70">Comprobantes de Pago Electrónicos</p>
            </div>
        </div>

        {{-- Chips de estado --}}
        <div class="relative mt-5 flex flex-wrap items-center gap-2">
            @if ($resumen['habilitado'])
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold">
                    <span class="h-2 w-2 rounded-full bg-emerald-300"></span> Habilitada
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-black/20 px-3 py-1 text-xs font-semibold">
                    <span class="h-2 w-2 rounded-full bg-slate-300"></span> Deshabilitada
                </span>
            @endif

            <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                Driver: {{ $resumen['driver'] === 'greenter' ? 'Greenter' : 'null' }}
            </span>
            <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                Modo: {{ $resumen['entorno'] === 'produccion' ? 'producción' : 'beta' }}
            </span>

            @if ($resumen['cert_existe'])
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/30 px-3 py-1 text-xs font-semibold">
                    ✓ Certificado cargado
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-500/30 px-3 py-1 text-xs font-semibold">
                    ✕ Certificado no encontrado
                </span>
            @endif

            @if ($resumen['driver'] === 'greenter' && ! $resumen['greenter'])
                <span class="rounded-full bg-amber-500/30 px-3 py-1 text-xs font-semibold">
                    ⚠ Greenter no instalado
                </span>
            @endif

            <form method="POST" action="{{ route('facturacion.probar') }}" class="sm:ml-auto">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-brand-700 shadow-sm hover:bg-white/90">
                    ⚡ Probar conexión con SUNAT
                </button>
            </form>
        </div>
    </div>

    {{-- ===================== FORMULARIO ===================== --}}
    <form method="POST" action="{{ route('facturacion.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- ---------- Estado y modo ---------- --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex items-start gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-100 text-brand-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </span>
                <div>
                    <h3 class="font-semibold text-slate-800">Estado y modo</h3>
                    <p class="text-sm text-slate-500">Activación, forma de emisión y entorno de SUNAT.</p>
                </div>
            </div>

            <div class="space-y-3">
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4 hover:bg-slate-50 cursor-pointer">
                    <input type="checkbox" name="fe_habilitado" value="1" @checked($cfg['fe_habilitado'] === '1')
                           class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span>
                        <span class="block text-sm font-medium text-slate-800">Habilitar facturación electrónica</span>
                        <span class="block text-xs text-slate-500">Si está desactivada, las ventas no generan comprobante ante SUNAT.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4 hover:bg-slate-50 cursor-pointer">
                    <input type="checkbox" name="fe_auto_emitir" value="1" @checked($cfg['fe_auto_emitir'] === '1')
                           class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span>
                        <span class="block text-sm font-medium text-slate-800">Emitir automáticamente al cerrar la venta</span>
                        <span class="block text-xs text-slate-500">Cada boleta o factura se envía apenas se registra en el POS.</span>
                    </span>
                </label>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Driver de emisión</label>
                    <select name="fe_driver" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                        <option value="null" @selected($cfg['fe_driver'] === 'null')>Ninguno (no emite, deja pendiente)</option>
                        <option value="greenter" @selected($cfg['fe_driver'] === 'greenter')>Greenter (emite ante SUNAT)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Entorno SUNAT</label>
                    <select name="fe_entorno" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                        <option value="beta" @selected($cfg['fe_entorno'] === 'beta')>Beta (homologación / pruebas)</option>
                        <option value="produccion" @selected($cfg['fe_entorno'] === 'produccion')>Producción</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- ---------- Datos del emisor ---------- --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex items-start gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-100 text-sky-600">
                    <x-icon name="building" />
                </span>
                <div>
                    <h3 class="font-semibold text-slate-800">Datos del emisor</h3>
                    <p class="text-sm text-slate-500">Aparecen en el comprobante electrónico.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">RUC <span class="text-red-500">*</span></label>
                    <input type="text" name="fe_ruc" value="{{ old('fe_ruc', $cfg['fe_ruc']) }}" maxlength="11" required
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Razón social <span class="text-red-500">*</span></label>
                    <input type="text" name="fe_razon_social" value="{{ old('fe_razon_social', $cfg['fe_razon_social']) }}" required
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nombre comercial</label>
                    <input type="text" name="fe_nombre_comercial" value="{{ old('fe_nombre_comercial', $cfg['fe_nombre_comercial']) }}"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Dirección fiscal</label>
                    <input type="text" name="fe_direccion" value="{{ old('fe_direccion', $cfg['fe_direccion']) }}"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Ubigeo</label>
                    <input type="text" name="fe_ubigeo" value="{{ old('fe_ubigeo', $cfg['fe_ubigeo']) }}" maxlength="6"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Departamento</label>
                    <input type="text" name="fe_departamento" value="{{ old('fe_departamento', $cfg['fe_departamento']) }}"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Provincia</label>
                    <input type="text" name="fe_provincia" value="{{ old('fe_provincia', $cfg['fe_provincia']) }}"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Distrito</label>
                    <input type="text" name="fe_distrito" value="{{ old('fe_distrito', $cfg['fe_distrito']) }}"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
            </div>
        </div>

        {{-- ---------- Series ---------- --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex items-start gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 text-violet-600">
                    <x-icon name="list" />
                </span>
                <div>
                    <h3 class="font-semibold text-slate-800">Series de comprobantes</h3>
                    <p class="text-sm text-slate-500">Series correlativas por tipo de comprobante.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Serie factura (F)</label>
                    <input type="text" name="fe_serie_factura" value="{{ old('fe_serie_factura', $cfg['fe_serie_factura']) }}" maxlength="5"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Serie boleta (B)</label>
                    <input type="text" name="fe_serie_boleta" value="{{ old('fe_serie_boleta', $cfg['fe_serie_boleta']) }}" maxlength="5"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Serie nota de crédito</label>
                    <input type="text" name="fe_serie_nota" value="{{ old('fe_serie_nota', $cfg['fe_serie_nota']) }}" maxlength="5"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
            </div>
        </div>

        {{-- ---------- Credenciales SUNAT ---------- --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex items-start gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H9v1.5H7.5v1.5H6l-1.5 1.5H3v-3l6.106-6.106c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
                </span>
                <div>
                    <h3 class="font-semibold text-slate-800">Credenciales SUNAT</h3>
                    <p class="text-sm text-slate-500">Clave SOL y certificado digital.</p>
                </div>
            </div>

            @if ($cfg['fe_entorno'] === 'beta')
                <div class="mb-4 flex items-start gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                    <span>En <strong>beta</strong> puedes usar RUC <strong>20000000001</strong> con usuario y clave <strong>MODDATOS</strong>.</span>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Usuario Clave SOL</label>
                    <input type="text" name="fe_sol_usuario" value="{{ old('fe_sol_usuario', $cfg['fe_sol_usuario']) }}"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Clave SOL</label>
                    <input type="password" name="fe_sol_clave" value="{{ old('fe_sol_clave', $cfg['fe_sol_clave']) }}"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700">Ruta del certificado (.pem)</label>
                    <input type="text" name="fe_certificado_ruta" value="{{ old('fe_certificado_ruta', $cfg['fe_certificado_ruta']) }}"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    @if ($resumen['cert_existe'])
                        <p class="mt-1 flex items-center gap-1 text-xs text-emerald-600">✓ Certificado encontrado en la ruta indicada.</p>
                    @else
                        <p class="mt-1 flex items-center gap-1 text-xs text-red-600">⚠ No se encontró el certificado en la ruta indicada.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- ---------- Barra de acciones ---------- --}}
        <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">← Volver</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                ✓ Guardar configuración
            </button>
        </div>
    </form>

    {{-- ===================== HISTORIAL ===================== --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                    <x-icon name="receipt" />
                </span>
                <div>
                    <h3 class="font-semibold text-slate-800">Comprobantes electrónicos recientes</h3>
                    <p class="text-sm text-slate-500">Últimos comprobantes emitidos y su estado ante SUNAT.</p>
                </div>
            </div>
            <a href="{{ route('facturacion.reporte') }}"
               class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                <x-icon name="chart" /> Reporte para contador
            </a>
        </div>

        @if ($comprobantes->isEmpty())
            <p class="rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-400">
                Aún no hay comprobantes electrónicos. Se generarán al registrar ventas con la facturación habilitada.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-400">
                            <th class="py-2 pr-3">Comprobante</th>
                            <th class="py-2 pr-3">Tipo</th>
                            <th class="py-2 pr-3">Cliente</th>
                            <th class="py-2 pr-3 text-right">Total</th>
                            <th class="py-2 pr-3">Estado</th>
                            <th class="py-2 pr-3">SUNAT</th>
                            <th class="py-2 pr-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($comprobantes as $c)
                            <tr class="hover:bg-slate-50">
                                <td class="py-2.5 pr-3 font-medium text-slate-700">{{ $c->numero }}</td>
                                <td class="py-2.5 pr-3 text-slate-600">{{ $c->tipoNombre() }}</td>
                                <td class="py-2.5 pr-3 text-slate-600">{{ \Illuminate\Support\Str::limit($c->cliente_nombre, 24) }}</td>
                                <td class="py-2.5 pr-3 text-right text-slate-700">S/ {{ number_format($c->total, 2) }}</td>
                                <td class="py-2.5 pr-3">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize {{ $c->estadoClase() }}">{{ $c->estado }}</span>
                                </td>
                                <td class="py-2.5 pr-3 text-xs text-slate-500">
                                    @if ($c->sunat_codigo)[{{ $c->sunat_codigo }}] @endif{{ \Illuminate\Support\Str::limit($c->sunat_descripcion, 40) }}
                                </td>
                                <td class="py-2.5 pr-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('facturacion.imprimir', $c) }}" target="_blank"
                                           class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100">PDF</a>
                                        @if (in_array($c->estado, ['pendiente', 'error', 'rechazado']))
                                            <form method="POST" action="{{ route('facturacion.reintentar', $c) }}">
                                                @csrf
                                                <button class="rounded-md bg-sky-50 px-2 py-1 text-xs font-medium text-sky-700 hover:bg-sky-100">Reintentar</button>
                                            </form>
                                        @endif
                                        @if ($c->esAceptado() && $c->tipo_comprobante !== '07')
                                            <form method="POST" action="{{ route('facturacion.nota', $c) }}"
                                                  onsubmit="return confirm('¿Emitir nota de crédito para anular {{ $c->numero }}?');">
                                                @csrf
                                                <button class="rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-100">Anular (NC)</button>
                                            </form>
                                        @endif
                                        @if ($c->xml_path)
                                            <a href="{{ route('facturacion.descargar', ['comprobante' => $c, 'tipo' => 'xml']) }}"
                                               class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-200">XML</a>
                                        @endif
                                        @if ($c->cdr_path)
                                            <a href="{{ route('facturacion.descargar', ['comprobante' => $c, 'tipo' => 'cdr']) }}"
                                               class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-200">CDR</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
