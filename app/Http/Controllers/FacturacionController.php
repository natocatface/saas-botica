<?php

namespace App\Http\Controllers;

use App\Mail\ComprobanteMail;
use App\Models\ComprobanteElectronico;
use App\Models\Configuracion;
use App\Services\Facturacion\FacturacionService;
use App\Support\FacturacionConfig;
use App\Support\NumeroALetras;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FacturacionController extends Controller
{
    public function __construct(private FacturacionService $facturacion)
    {
    }

    /** Módulo de configuración de Facturación Electrónica. */
    public function index(): View
    {
        $comprobantes = ComprobanteElectronico::with('venta')
            ->latest()
            ->limit(15)
            ->get();

        return view('facturacion.configuracion', [
            'cfg' => FacturacionConfig::todas(),
            'resumen' => FacturacionConfig::resumen(),
            'comprobantes' => $comprobantes,
        ]);
    }

    /** Guarda la configuración del módulo. */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fe_ruc' => 'required|string|size:11',
            'fe_razon_social' => 'required|string|max:250',
            'fe_nombre_comercial' => 'nullable|string|max:250',
            'fe_direccion' => 'nullable|string|max:250',
            'fe_ubigeo' => 'nullable|string|max:6',
            'fe_departamento' => 'nullable|string|max:60',
            'fe_provincia' => 'nullable|string|max:60',
            'fe_distrito' => 'nullable|string|max:60',
            'fe_driver' => 'required|in:null,greenter',
            'fe_entorno' => 'required|in:beta,produccion',
            'fe_sol_usuario' => 'nullable|string|max:60',
            'fe_sol_clave' => 'nullable|string|max:100',
            'fe_certificado_ruta' => 'nullable|string|max:255',
            'fe_serie_factura' => 'nullable|string|max:5',
            'fe_serie_boleta' => 'nullable|string|max:5',
            'fe_serie_nota' => 'nullable|string|max:5',
        ], [
            'fe_ruc.size' => 'El RUC debe tener 11 dígitos.',
            'fe_razon_social.required' => 'La razón social es obligatoria.',
        ]);

        // Checkboxes (no llegan si están desmarcados).
        $data['fe_habilitado'] = $request->boolean('fe_habilitado') ? '1' : '0';
        $data['fe_auto_emitir'] = $request->boolean('fe_auto_emitir') ? '1' : '0';

        FacturacionConfig::guardar($data);

        return redirect()->route('facturacion.index')
            ->with('ok', 'Configuración de facturación electrónica guardada.');
    }

    /** Prueba de conexión con SUNAT (según driver activo). */
    public function probar(): RedirectResponse
    {
        $r = $this->facturacion->probarConexion();

        return redirect()->route('facturacion.index')
            ->with($r->ok ? 'ok' : 'error', 'Prueba SUNAT: ' . ($r->mensaje ?? ($r->ok ? 'OK' : 'Falló')));
    }

    /** Reintenta emitir un comprobante pendiente / con error. */
    public function reintentar(ComprobanteElectronico $comprobante): RedirectResponse
    {
        $this->facturacion->reintentar($comprobante);

        return redirect()->route('facturacion.index')
            ->with('ok', "Comprobante {$comprobante->numero}: {$comprobante->estado}. {$comprobante->sunat_descripcion}");
    }

    /** Emite una nota de crédito que anula un comprobante aceptado. */
    public function notaCredito(Request $request, ComprobanteElectronico $comprobante): RedirectResponse
    {
        $request->validate(['motivo' => 'nullable|string|max:250']);

        if (! $comprobante->esAceptado()) {
            return back()->with('error', 'Solo se puede anular un comprobante aceptado por SUNAT.');
        }

        $nota = $this->facturacion->emitirNotaCredito(
            $comprobante,
            $request->input('motivo', 'ANULACION DE LA OPERACION')
        );

        return redirect()->route('facturacion.index')
            ->with('ok', "Nota de crédito {$nota->numero}: {$nota->estado}. {$nota->sunat_descripcion}");
    }

    /** Reporte de comprobantes por rango de fechas (para contabilidad). */
    public function reporte(Request $request): View
    {
        [$desde, $hasta, $tipo, $estado] = $this->filtrosReporte($request);

        $items = $this->reporteQuery($desde, $hasta, $tipo, $estado)
            ->with('venta')
            ->orderBy('created_at')
            ->get();

        $aceptados = $items->where('estado', 'aceptado');

        return view('facturacion.reporte', [
            'items'   => $items,
            'desde'   => $desde,
            'hasta'   => $hasta,
            'tipo'    => $tipo,
            'estado'  => $estado,
            'cfg'     => FacturacionConfig::todas(),
            'kpis'    => [
                'num'        => $items->count(),
                'aceptados'  => $aceptados->count(),
                'pendientes' => $items->whereIn('estado', ['pendiente', 'enviado'])->count(),
                'incidencias' => $items->whereIn('estado', ['rechazado', 'observado', 'error'])->count(),
                'gravado'    => (float) $aceptados->sum('gravado'),
                'igv'        => (float) $aceptados->sum('igv'),
                'total'      => (float) $aceptados->sum('total'),
            ],
        ]);
    }

    /** Exporta el reporte a CSV (se abre en Excel), con BOM para acentos. */
    public function exportarReporte(Request $request): StreamedResponse
    {
        [$desde, $hasta, $tipo, $estado] = $this->filtrosReporte($request);

        $items = $this->reporteQuery($desde, $hasta, $tipo, $estado)
            ->with('venta')
            ->orderBy('created_at')
            ->get();

        $tiposDoc = ['6' => 'RUC', '1' => 'DNI', '4' => 'CE', '0' => 'Sin doc'];
        $headers = ['Fecha emisión', 'Tipo', 'Serie-Número', 'Tipo doc.', 'N° documento', 'Cliente',
            'Moneda', 'Op. Gravada', 'IGV', 'Total', 'Estado', 'Cód. SUNAT'];

        $filas = $items->map(fn ($c) => [
            ($c->enviado_at ?? $c->created_at)->format('d/m/Y'),
            $c->tipoNombre(),
            $c->numero,
            $tiposDoc[$c->cliente_tipo_doc] ?? '-',
            $c->cliente_num_doc ?: '-',
            $c->cliente_nombre ?: 'CLIENTE VARIOS',
            $c->moneda,
            number_format((float) $c->gravado, 2, '.', ''),
            number_format((float) $c->igv, 2, '.', ''),
            number_format((float) $c->total, 2, '.', ''),
            ucfirst($c->estado),
            $c->sunat_codigo ?? '',
        ])->all();

        $nombre = "comprobantes_{$desde}_a_{$hasta}.csv";

        return response()->streamDownload(function () use ($headers, $filas) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF"); // BOM para acentos en Excel
            fputcsv($out, $headers);
            foreach ($filas as $fila) {
                fputcsv($out, $fila);
            }
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0:string,1:string,2:string,3:string} [desde, hasta, tipo, estado] */
    private function filtrosReporte(Request $request): array
    {
        $desde = $request->query('desde') ?: now()->startOfMonth()->toDateString();
        $hasta = $request->query('hasta') ?: now()->toDateString();
        $tipo = in_array($request->query('tipo'), ['01', '03', '07'], true) ? $request->query('tipo') : '';
        $estado = in_array($request->query('estado'), ['aceptado', 'pendiente', 'enviado', 'observado', 'rechazado', 'error', 'anulado'], true)
            ? $request->query('estado') : '';

        return [$desde, $hasta, $tipo, $estado];
    }

    private function reporteQuery(string $desde, string $hasta, string $tipo, string $estado)
    {
        return ComprobanteElectronico::query()
            ->whereDate('created_at', '>=', $desde)
            ->whereDate('created_at', '<=', $hasta)
            ->when($tipo !== '', fn ($q) => $q->where('tipo_comprobante', $tipo))
            ->when($estado !== '', fn ($q) => $q->where('estado', $estado));
    }

    /** Representación impresa (A4) del comprobante, con QR y hash de SUNAT. */
    public function imprimir(ComprobanteElectronico $comprobante): View
    {
        $comprobante->load(['venta.detalles', 'venta.cliente', 'referencia']);

        $igvPct = (float) Configuracion::valor('igv_porcentaje', 18);
        $fecha = ($comprobante->enviado_at ?? $comprobante->created_at)->format('Y-m-d');

        // Cadena del QR según especificación SUNAT.
        $qr = implode('|', [
            $comprobante->ruc_emisor,
            $comprobante->tipo_comprobante,
            $comprobante->serie,
            $comprobante->correlativo,
            number_format((float) $comprobante->igv, 2, '.', ''),
            number_format((float) $comprobante->total, 2, '.', ''),
            $fecha,
            $comprobante->cliente_tipo_doc,
            $comprobante->cliente_num_doc,
            $comprobante->hash_cpe ?? '',
        ]);

        return view('facturacion.comprobante', [
            'c' => $comprobante,
            'cfg' => FacturacionConfig::todas(),
            'igvPct' => $igvPct,
            'qr' => $qr,
            'enLetras' => NumeroALetras::soles((float) $comprobante->total),
            'emailCliente' => $comprobante->venta?->cliente?->email,
        ]);
    }

    /** Envía el comprobante (XML + CDR + PDF opcional) por correo al cliente. */
    public function enviarCorreo(Request $request, ComprobanteElectronico $comprobante): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email'], [
            'email.required' => 'Indica un correo de destino.',
            'email.email' => 'El correo no es válido.',
        ]);

        try {
            Mail::to($data['email'])->send(new ComprobanteMail($comprobante));
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo enviar el correo: ' . $e->getMessage());
        }

        return back()->with('ok', "Comprobante {$comprobante->numero} enviado a {$data['email']}.");
    }

    /** Descarga el XML firmado o el CDR de un comprobante. */
    public function descargar(ComprobanteElectronico $comprobante, string $tipo): mixed
    {
        $ruta = $tipo === 'cdr' ? $comprobante->cdr_path : $comprobante->xml_path;
        if (! $ruta || ! is_file(storage_path($ruta))) {
            return back()->with('error', 'Archivo no disponible.');
        }

        return Response::download(storage_path($ruta));
    }
}
