<?php

namespace App\Services\Facturacion;

use App\Models\ComprobanteElectronico;
use App\Models\Venta;
use App\Services\Facturacion\Drivers\GreenterEmisor;
use App\Services\Facturacion\Drivers\NullEmisor;
use App\Support\FacturacionConfig;
use Illuminate\Support\Facades\Log;

/**
 * FacturacionService
 * ------------------
 * Orquesta la emisión electrónica: resuelve el driver configurado, construye
 * el comprobante a partir de la venta, lo envía a SUNAT y persiste el estado
 * y los archivos (XML firmado / CDR).
 */
class FacturacionService
{
    public function driver(): EmisorInterface
    {
        return match (FacturacionConfig::driver()) {
            'greenter' => new GreenterEmisor(),
            default => new NullEmisor(),
        };
    }

    /** Prueba de conexión con SUNAT según el driver activo. */
    public function probarConexion(): ResultadoEmision
    {
        return $this->driver()->probarConexion();
    }

    /**
     * Emite (o registra) el comprobante electrónico de una venta.
     * Es idempotente: si la venta ya tiene un comprobante aceptado, no reemite.
     * Nunca lanza excepción: los errores quedan registrados en el comprobante.
     */
    public function emitirVenta(Venta $venta): ?ComprobanteElectronico
    {
        $tipo = $this->tipoSunat($venta->tipo_comprobante);
        if ($tipo === null) {
            return null; // ticket u otro: no genera CPE electrónico
        }

        // Reutiliza el comprobante existente de la venta (evita duplicar correlativos).
        $existente = ComprobanteElectronico::where('venta_id', $venta->id)
            ->whereIn('tipo_comprobante', ['01', '03'])
            ->latest('id')
            ->first();

        if ($existente) {
            // Ya aceptado/en curso: no se reemite.
            if (in_array($existente->estado, ['aceptado', 'enviado', 'observado'], true)) {
                return $existente;
            }
            // Pendiente / error / rechazado: se reintenta el MISMO comprobante.
            return $this->reintentar($existente);
        }

        $comprobante = $this->crearComprobante($venta, $tipo);

        try {
            $resultado = $this->driver()->emitir($comprobante);
            $this->aplicarResultado($comprobante, $resultado);
        } catch (\Throwable $e) {
            $comprobante->update([
                'estado' => 'error',
                'sunat_descripcion' => 'Excepción: ' . $e->getMessage(),
            ]);
            Log::error('FE emitirVenta', ['venta' => $venta->id, 'error' => $e->getMessage()]);
        }

        return $comprobante;
    }

    /** Reintenta la emisión de un comprobante pendiente / con error. */
    public function reintentar(ComprobanteElectronico $comprobante): ComprobanteElectronico
    {
        try {
            $resultado = $comprobante->tipo_comprobante === '07' && $comprobante->referencia
                ? $this->driver()->emitirNotaCredito($comprobante, $comprobante->referencia)
                : $this->driver()->emitir($comprobante);
            $this->aplicarResultado($comprobante, $resultado);
        } catch (\Throwable $e) {
            $comprobante->update(['estado' => 'error', 'sunat_descripcion' => $e->getMessage()]);
        }

        return $comprobante;
    }

    /**
     * Emite una nota de crédito (tipo 07) que anula un comprobante aceptado.
     */
    public function emitirNotaCredito(ComprobanteElectronico $referencia, string $motivo = 'ANULACION DE LA OPERACION'): ComprobanteElectronico
    {
        $serie = FacturacionConfig::serie('07');
        $correlativo = $this->siguienteCorrelativo($serie);

        $nota = ComprobanteElectronico::create([
            'venta_id' => $referencia->venta_id,
            'tipo_comprobante' => '07',
            'serie' => $serie,
            'correlativo' => $correlativo,
            'numero' => $serie . '-' . str_pad((string) $correlativo, 6, '0', STR_PAD_LEFT),
            'ruc_emisor' => FacturacionConfig::get('fe_ruc'),
            'cliente_tipo_doc' => $referencia->cliente_tipo_doc,
            'cliente_num_doc' => $referencia->cliente_num_doc,
            'cliente_nombre' => $referencia->cliente_nombre,
            'moneda' => $referencia->moneda,
            'gravado' => $referencia->gravado,
            'igv' => $referencia->igv,
            'total' => $referencia->total,
            'estado' => 'pendiente',
            'entorno' => FacturacionConfig::entorno(),
            'driver' => FacturacionConfig::driver(),
            'referencia_id' => $referencia->id,
            'motivo_nota' => $motivo,
        ]);

        try {
            $resultado = $this->driver()->emitirNotaCredito($nota, $referencia);
            $this->aplicarResultado($nota, $resultado);
            if ($nota->esAceptado()) {
                $referencia->update(['estado' => 'anulado']);
            }
        } catch (\Throwable $e) {
            $nota->update(['estado' => 'error', 'sunat_descripcion' => $e->getMessage()]);
        }

        return $nota;
    }

    // ----------------------------------------------------------------

    private function crearComprobante(Venta $venta, string $tipo): ComprobanteElectronico
    {
        $serie = FacturacionConfig::serie($tipo);
        $correlativo = $this->siguienteCorrelativo($serie);
        [$tipoDoc, $numDoc, $nombre] = $this->datosCliente($venta);

        return ComprobanteElectronico::create([
            'venta_id' => $venta->id,
            'tipo_comprobante' => $tipo,
            'serie' => $serie,
            'correlativo' => $correlativo,
            'numero' => $serie . '-' . str_pad((string) $correlativo, 6, '0', STR_PAD_LEFT),
            'ruc_emisor' => FacturacionConfig::get('fe_ruc'),
            'cliente_tipo_doc' => $tipoDoc,
            'cliente_num_doc' => $numDoc,
            'cliente_nombre' => $nombre,
            'moneda' => 'PEN',
            'gravado' => $venta->subtotal,
            'igv' => $venta->igv,
            'total' => $venta->total,
            'estado' => 'pendiente',
            'entorno' => FacturacionConfig::entorno(),
            'driver' => FacturacionConfig::driver(),
        ]);
    }

    private function aplicarResultado(ComprobanteElectronico $comprobante, ResultadoEmision $r): void
    {
        $datos = [
            'estado' => $r->estado,
            'sunat_codigo' => $r->codigo,
            'sunat_descripcion' => $r->mensaje ? mb_substr($r->mensaje, 0, 500) : null,
            'hash_cpe' => $r->hash,
            'ticket' => $r->ticket,
            'enviado_at' => now(),
        ];

        if ($r->xml) {
            $datos['xml_path'] = $this->guardarArchivo('xml', $comprobante->numero . '.xml', $r->xml);
        }
        if ($r->cdr) {
            $datos['cdr_path'] = $this->guardarArchivo('cdr', 'R-' . $comprobante->numero . '.zip', $r->cdr);
        }

        $comprobante->update($datos);
    }

    private function guardarArchivo(string $sub, string $nombre, string $contenido): ?string
    {
        try {
            $dir = storage_path('facturacion/pe/' . $sub);
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $ruta = $dir . DIRECTORY_SEPARATOR . $nombre;
            file_put_contents($ruta, $contenido);

            return 'facturacion/pe/' . $sub . '/' . $nombre;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function siguienteCorrelativo(string $serie): int
    {
        $max = (int) ComprobanteElectronico::where('serie', $serie)->max('correlativo');

        return $max + 1;
    }

    /** ticket → null (no CPE) | boleta → 03 | factura → 01 */
    private function tipoSunat(?string $tipoVenta): ?string
    {
        return match ($tipoVenta) {
            'factura' => '01',
            'boleta' => '03',
            default => null,
        };
    }

    /** @return array{0:string,1:string,2:string} [tipoDocSunat, numDoc, nombre] */
    private function datosCliente(Venta $venta): array
    {
        $cliente = $venta->cliente;
        if (! $cliente) {
            return ['0', '-', 'CLIENTE VARIOS'];
        }

        $tipo = match (strtoupper((string) $cliente->tipo_documento)) {
            'RUC' => '6',
            'DNI' => '1',
            'CE' => '4',
            default => '0',
        };

        return [
            $tipo,
            $cliente->numero_documento ?: '-',
            $cliente->nombre ?: 'CLIENTE VARIOS',
        ];
    }
}
