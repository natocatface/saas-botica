<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Venta;
use App\Services\Facturacion\FacturacionService;
use App\Support\FacturacionConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class VentaController extends Controller
{
    public function index(Request $request): View
    {
        $buscar = trim((string) $request->query('q', ''));
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');
        $metodo = $request->query('metodo');
        $estado = $request->query('estado');
        $cajeroId = $request->query('cajero');

        $base = Venta::query()
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($w) use ($buscar) {
                    $w->where('numero_comprobante', 'like', "%{$buscar}%")
                        ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$buscar}%"));
                });
            })
            ->when($desde, fn ($q) => $q->whereDate('created_at', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('created_at', '<=', $hasta))
            ->when($metodo, fn ($q) => $q->where('metodo_pago', $metodo))
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($cajeroId, fn ($q) => $q->where('user_id', $cajeroId));

        // Tarjetas resumen sobre el conjunto filtrado (solo pagadas para montos)
        $resumen = (clone $base)->where('estado', 'pagada');
        $totalVendido = (float) (clone $resumen)->sum('total');
        $numVentas = (clone $base)->count();
        $numPagadas = (clone $resumen)->count();
        $ticketPromedio = $numPagadas > 0 ? $totalVendido / $numPagadas : 0;

        // Facturación electrónica: solo si el módulo ya fue migrado.
        $feListo = Schema::hasTable('comprobantes_electronicos');
        $feHabilitado = $feListo && FacturacionConfig::habilitado();

        $relaciones = ['cliente', 'usuario'];
        if ($feListo) {
            $relaciones[] = 'comprobante';
        }

        $ventas = $base->with($relaciones)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $cajeros = User::orderBy('name')->get(['id', 'name']);

        return view('ventas.index', compact(
            'ventas', 'buscar', 'desde', 'hasta', 'metodo', 'estado', 'cajeroId',
            'cajeros', 'totalVendido', 'numVentas', 'ticketPromedio', 'feListo', 'feHabilitado'
        ));
    }

    /** Emite (o reintenta) el comprobante electrónico de una venta existente. */
    public function emitir(Venta $venta, FacturacionService $facturacion): RedirectResponse
    {
        if (! FacturacionConfig::habilitado()) {
            return back()->with('error', 'La facturación electrónica está deshabilitada. Actívala en su configuración.');
        }
        if ($venta->estado !== 'pagada') {
            return back()->with('error', 'Solo se emiten comprobantes de ventas pagadas.');
        }
        if (! in_array($venta->tipo_comprobante, ['boleta', 'factura'], true)) {
            return back()->with('error', 'Esta venta es un ticket interno; no genera comprobante electrónico.');
        }

        $comprobante = $facturacion->emitirVenta($venta);

        if (! $comprobante) {
            return back()->with('error', 'No se pudo generar el comprobante.');
        }

        $tipo = $comprobante->esAceptado() ? 'ok' : ($comprobante->estado === 'pendiente' ? 'ok' : 'error');

        return back()->with($tipo, "Comprobante {$comprobante->numero}: {$comprobante->estado}. {$comprobante->sunat_descripcion}");
    }

    public function anular(Venta $venta): RedirectResponse
    {
        if ($venta->estado === 'anulada') {
            return back()->with('error', 'La venta ya estaba anulada.');
        }

        DB::transaction(function () use ($venta) {
            // Restaurar stock de cada producto
            foreach ($venta->detalles()->with('producto')->get() as $detalle) {
                if ($detalle->producto) {
                    $detalle->producto->increment('stock', $detalle->cantidad);
                }
            }
            $venta->update(['estado' => 'anulada']);
        });

        return back()->with('ok', "Venta {$venta->numero_comprobante} anulada y stock restaurado.");
    }
}
