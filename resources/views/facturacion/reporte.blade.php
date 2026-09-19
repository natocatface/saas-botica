@extends('layouts.app')
@section('titulo', 'Reporte de Comprobantes')

@section('contenido')
<div class="max-w-7xl mx-auto space-y-6">

    {{-- Encabezado --}}
    <div class="flex flex-wrap items-center justify-between gap-3 print-header">
        <div>
            <nav class="text-xs text-slate-400 mb-1 no-print">Ajustes & Sistema · Facturación</nav>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-brand-100 text-brand-600 flex items-center justify-center no-print"><x-icon name="chart" /></span>
                Reporte de Comprobantes Electrónicos
            </h1>
            <p class="text-sm text-slate-500">
                {{ $cfg['fe_razon_social'] }} · RUC {{ $cfg['fe_ruc'] }} ·
                del {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
            </p>
        </div>
        <div class="flex items-center gap-2 no-print">
            <a href="{{ route('facturacion.reporte.export', request()->query()) }}"
               class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Excel (CSV)
            </a>
            <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-900">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0V4.125C18.75 3.504 18.246 3 17.625 3H6.375C5.754 3 5.25 3.504 5.25 4.125v2.909m13.5 0a48.536 48.536 0 00-13.5 0"/></svg>
                Imprimir / PDF
            </button>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('facturacion.reporte') }}" class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 no-print">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Desde</label>
            <input type="date" name="desde" value="{{ $desde }}" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:bg-white focus:border-brand-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta }}" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:bg-white focus:border-brand-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Tipo</label>
            <select name="tipo" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:bg-white focus:border-brand-500 focus:outline-none">
                <option value="">Todos</option>
                <option value="01" @selected($tipo==='01')>Factura</option>
                <option value="03" @selected($tipo==='03')>Boleta</option>
                <option value="07" @selected($tipo==='07')>Nota de crédito</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Estado</label>
            <select name="estado" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:bg-white focus:border-brand-500 focus:outline-none">
                <option value="">Todos</option>
                @foreach (['aceptado','pendiente','enviado','observado','rechazado','error','anulado'] as $e)
                    <option value="{{ $e }}" @selected($estado===$e)>{{ ucfirst($e) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Aplicar</button>
            <a href="{{ route('facturacion.reporte') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100">Limpiar</a>
        </div>
    </form>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Comprobantes</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-800">{{ number_format($kpis['num']) }}</p>
            <p class="text-xs text-slate-400">{{ $kpis['aceptados'] }} aceptados · {{ $kpis['pendientes'] }} pend. · {{ $kpis['incidencias'] }} inc.</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Op. Gravada (aceptados)</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-800">S/ {{ number_format($kpis['gravado'], 2) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">IGV (aceptados)</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-800">S/ {{ number_format($kpis['igv'], 2) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total (aceptados)</p>
            <p class="mt-1 text-2xl font-extrabold text-brand-600">S/ {{ number_format($kpis['total'], 2) }}</p>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Fecha</th>
                        <th class="px-4 py-3 font-semibold">Tipo</th>
                        <th class="px-4 py-3 font-semibold">Número</th>
                        <th class="px-4 py-3 font-semibold">Cliente</th>
                        <th class="px-4 py-3 font-semibold text-right">Op. Gravada</th>
                        <th class="px-4 py-3 font-semibold text-right">IGV</th>
                        <th class="px-4 py-3 font-semibold text-right">Total</th>
                        <th class="px-4 py-3 font-semibold text-center">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($items as $c)
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-4 py-2.5 text-slate-500">{{ ($c->enviado_at ?? $c->created_at)->format('d/m/Y') }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $c->tipoNombre() }}</td>
                            <td class="px-4 py-2.5 font-mono text-slate-700">{{ $c->numero }}</td>
                            <td class="px-4 py-2.5 text-slate-600">
                                {{ \Illuminate\Support\Str::limit($c->cliente_nombre, 30) }}
                                <span class="text-xs text-slate-400">{{ $c->cliente_num_doc }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right text-slate-600">{{ number_format($c->gravado, 2) }}</td>
                            <td class="px-4 py-2.5 text-right text-slate-600">{{ number_format($c->igv, 2) }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold text-slate-700">{{ number_format($c->total, 2) }}</td>
                            <td class="px-4 py-2.5 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold capitalize {{ $c->estadoClase() }}">{{ $c->estado }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">No hay comprobantes en el rango seleccionado.</td></tr>
                    @endforelse
                </tbody>
                @if ($items->isNotEmpty())
                    <tfoot>
                        <tr class="bg-slate-50 font-bold text-slate-700 border-t-2 border-slate-200">
                            <td class="px-4 py-3" colspan="4">Totales aceptados ({{ $kpis['aceptados'] }})</td>
                            <td class="px-4 py-3 text-right">{{ number_format($kpis['gravado'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($kpis['igv'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($kpis['total'], 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <p class="text-xs text-slate-400 no-print">
        Los totales de Op. Gravada, IGV y Total consideran solo comprobantes <strong>aceptados</strong> por SUNAT (declarables).
        Las notas de crédito figuran con su importe; considérelas como resta en la liquidación.
    </p>
</div>
@endsection

@push('scripts')
<style>
    @media print {
        aside, header, footer, .no-print { display: none !important; }
        .lg\:pl-64 { padding-left: 0 !important; }
        main { padding: 0 !important; }
        body { background: #fff !important; }
        @page { size: A4 landscape; margin: 12mm; }
    }
</style>
@endpush
