<?php

namespace App\Services\Facturacion\Drivers;

use App\Models\ComprobanteElectronico;
use App\Services\Facturacion\EmisorInterface;
use App\Services\Facturacion\ResultadoEmision;
use App\Support\FacturacionConfig;

/**
 * GreenterEmisor
 * --------------
 * Motor de emisión electrónica real ante SUNAT usando la librería Greenter
 * (UBL 2.1, firmado XAdES y envío SOAP al billService).
 *
 * La clase está protegida con class_exists(): si Greenter aún no se instaló
 * (composer require greenter/lite) o falta el certificado, devuelve un
 * ResultadoEmision de error legible en lugar de romper el sistema.
 */
class GreenterEmisor implements EmisorInterface
{
    public function nombre(): string
    {
        return 'greenter';
    }

    public function emitir(ComprobanteElectronico $comprobante): ResultadoEmision
    {
        if (! $this->disponible($msg)) {
            return ResultadoEmision::error($msg);
        }

        try {
            $see = $this->crearSee();
            $doc = $this->construirInvoice($comprobante);

            $result = $see->send($doc);
            $xml = $see->getXmlSigned($doc);

            return $this->interpretar($result, $xml);
        } catch (\Throwable $e) {
            return ResultadoEmision::error('Error al emitir: ' . $e->getMessage());
        }
    }

    public function emitirNotaCredito(ComprobanteElectronico $nota, ComprobanteElectronico $referencia): ResultadoEmision
    {
        if (! $this->disponible($msg)) {
            return ResultadoEmision::error($msg);
        }

        try {
            $see = $this->crearSee();
            $doc = $this->construirNota($nota, $referencia);

            $result = $see->send($doc);
            $xml = $see->getXmlSigned($doc);

            return $this->interpretar($result, $xml);
        } catch (\Throwable $e) {
            return ResultadoEmision::error('Error al emitir nota de crédito: ' . $e->getMessage());
        }
    }

    public function probarConexion(): ResultadoEmision
    {
        if (! class_exists(\Greenter\See::class)) {
            return ResultadoEmision::error(
                'Greenter no está instalado. Ejecuta: composer require greenter/lite'
            );
        }
        if (! FacturacionConfig::certificadoExiste()) {
            return ResultadoEmision::error(
                'No se encontró el certificado en: ' . FacturacionConfig::certificadoRuta()
            );
        }

        // Prueba de conectividad TCP contra el endpoint SOAP de SUNAT (sin emitir).
        $endpoint = $this->endpoint();
        $host = parse_url($endpoint, PHP_URL_HOST) ?: 'e-beta.sunat.gob.pe';

        $conn = @fsockopen('ssl://' . $host, 443, $errno, $errstr, 8);
        if (! $conn) {
            return ResultadoEmision::error("No se pudo conectar a SUNAT ({$host}:443): {$errstr}");
        }
        fclose($conn);

        $entorno = FacturacionConfig::esBeta() ? 'BETA (homologación)' : 'PRODUCCIÓN';

        return ResultadoEmision::ok(
            'aceptado',
            '0',
            "Conexión establecida con SUNAT {$entorno} en {$host}. Certificado y credenciales cargados."
        );
    }

    // ----------------------------------------------------------------
    // Construcción de documentos Greenter
    // ----------------------------------------------------------------

    private function crearSee(): \Greenter\See
    {
        $see = new \Greenter\See();
        $see->setCertificate(file_get_contents(FacturacionConfig::certificadoRuta()));
        $see->setService($this->endpoint());
        $see->setClaveSOL(
            (string) FacturacionConfig::get('fe_ruc'),
            (string) FacturacionConfig::get('fe_sol_usuario'),
            (string) FacturacionConfig::get('fe_sol_clave'),
        );

        return $see;
    }

    private function endpoint(): string
    {
        return FacturacionConfig::esBeta()
            ? \Greenter\Ws\Services\SunatEndpoints::FE_BETA
            : \Greenter\Ws\Services\SunatEndpoints::FE_PRODUCCION;
    }

    private function company(): \Greenter\Model\Company\Company
    {
        $address = (new \Greenter\Model\Company\Address())
            ->setUbigueo((string) FacturacionConfig::get('fe_ubigeo'))
            ->setDepartamento((string) FacturacionConfig::get('fe_departamento'))
            ->setProvincia((string) FacturacionConfig::get('fe_provincia'))
            ->setDistrito((string) FacturacionConfig::get('fe_distrito'))
            ->setUrbanizacion('-')
            ->setDireccion((string) FacturacionConfig::get('fe_direccion'));

        return (new \Greenter\Model\Company\Company())
            ->setRuc((string) FacturacionConfig::get('fe_ruc'))
            ->setRazonSocial((string) FacturacionConfig::get('fe_razon_social'))
            ->setNombreComercial((string) FacturacionConfig::get('fe_nombre_comercial'))
            ->setAddress($address);
    }

    private function client(ComprobanteElectronico $c): \Greenter\Model\Client\Client
    {
        return (new \Greenter\Model\Client\Client())
            ->setTipoDoc($c->cliente_tipo_doc ?: '0')
            ->setNumDoc($c->cliente_num_doc ?: '-')
            ->setRznSocial($c->cliente_nombre ?: 'CLIENTE VARIOS');
    }

    /**
     * Construye los detalles a partir de la venta asociada, distribuyendo el
     * descuento global de forma proporcional para que los totales cuadren.
     *
     * @return array{details: array, gravado: float, igv: float, total: float}
     */
    private function detallesDeVenta(ComprobanteElectronico $c): array
    {
        $venta = $c->venta;
        $lineas = $venta ? $venta->detalles()->with('producto')->get() : collect();

        $brutoLineas = 0.0;
        foreach ($lineas as $l) {
            $brutoLineas += (float) $l->precio_unitario * (int) $l->cantidad;
        }
        $factor = ($brutoLineas > 0 && (float) $venta->total > 0)
            ? (float) $venta->total / $brutoLineas
            : 1.0;

        $details = [];
        $gravado = 0.0;
        $igvTotal = 0.0;

        foreach ($lineas as $i => $l) {
            $cant = max(1, (int) $l->cantidad);
            $precioUnitConIgv = round((float) $l->precio_unitario * $factor, 2);   // precio final con IGV
            $valorUnitSinIgv = round($precioUnitConIgv / 1.18, 6);                 // valor sin IGV
            $valorVenta = round($valorUnitSinIgv * $cant, 2);                      // base de la línea
            $igvLinea = round($valorVenta * 0.18, 2);

            $gravado += $valorVenta;
            $igvTotal += $igvLinea;

            $details[] = (new \Greenter\Model\Sale\SaleDetail())
                ->setCodProducto((string) ($l->producto?->codigo_barras ?? ('P' . ($l->producto_id ?: $i + 1))))
                ->setUnidad('NIU')
                ->setCantidad($cant)
                ->setDescripcion((string) ($l->descripcion ?: ($l->producto?->nombre ?? 'Producto')))
                ->setMtoBaseIgv($valorVenta)
                ->setPorcentajeIgv(18.0)
                ->setIgv($igvLinea)
                ->setTipAfeIgv('10') // Gravado - Operación Onerosa
                ->setTotalImpuestos($igvLinea)
                ->setMtoValorVenta($valorVenta)
                ->setMtoValorUnitario($valorUnitSinIgv)
                ->setMtoPrecioUnitario($precioUnitConIgv);
        }

        $gravado = round($gravado, 2);
        $igvTotal = round($igvTotal, 2);

        return [
            'details' => $details,
            'gravado' => $gravado,
            'igv' => $igvTotal,
            'total' => round($gravado + $igvTotal, 2),
        ];
    }

    private function construirInvoice(ComprobanteElectronico $c): \Greenter\Model\Sale\Invoice
    {
        $d = $this->detallesDeVenta($c);

        $leyenda = (new \Greenter\Model\Sale\Legend())
            ->setCode('1000')
            ->setValue('SON ' . $this->numeroALetras($d['total']) . ' SOLES');

        return (new \Greenter\Model\Sale\Invoice())
            ->setUblVersion('2.1')
            ->setTipoOperacion('0101') // Venta interna
            ->setTipoDoc($c->tipo_comprobante) // 01 factura / 03 boleta
            ->setSerie($c->serie)
            ->setCorrelativo((string) $c->correlativo)
            ->setFechaEmision(new \DateTime())
            ->setFormaPago(new \Greenter\Model\Sale\FormaPagos\FormaPagoContado())
            ->setTipoMoneda($c->moneda ?: 'PEN')
            ->setCompany($this->company())
            ->setClient($this->client($c))
            ->setMtoOperGravadas($d['gravado'])
            ->setMtoIGV($d['igv'])
            ->setTotalImpuestos($d['igv'])
            ->setValorVenta($d['gravado'])
            ->setSubTotal($d['total'])
            ->setMtoImpVenta($d['total'])
            ->setDetails($d['details'])
            ->setLegends([$leyenda]);
    }

    private function construirNota(ComprobanteElectronico $nota, ComprobanteElectronico $ref): \Greenter\Model\Sale\Note
    {
        $d = $this->detallesDeVenta($ref);

        $leyenda = (new \Greenter\Model\Sale\Legend())
            ->setCode('1000')
            ->setValue('SON ' . $this->numeroALetras($d['total']) . ' SOLES');

        return (new \Greenter\Model\Sale\Note())
            ->setUblVersion('2.1')
            ->setTipoDoc('07') // Nota de crédito
            ->setSerie($nota->serie)
            ->setCorrelativo((string) $nota->correlativo)
            ->setFechaEmision(new \DateTime())
            ->setTipDocAfectado($ref->tipo_comprobante)
            ->setNumDocfectado($ref->numero)
            ->setCodMotivo('01') // Catálogo 09: Anulación de la operación
            ->setDesMotivo($nota->motivo_nota ?: 'ANULACION DE LA OPERACION')
            ->setTipoMoneda($nota->moneda ?: 'PEN')
            ->setCompany($this->company())
            ->setClient($this->client($nota))
            ->setMtoOperGravadas($d['gravado'])
            ->setMtoIGV($d['igv'])
            ->setTotalImpuestos($d['igv'])
            ->setMtoImpVenta($d['total'])
            ->setDetails($d['details'])
            ->setLegends([$leyenda]);
    }

    // ----------------------------------------------------------------
    // Interpretación de la respuesta de SUNAT
    // ----------------------------------------------------------------

    private function interpretar($result, ?string $xml): ResultadoEmision
    {
        $hash = null;
        if ($xml) {
            // DigestValue del XML firmado (útil para el resumen del comprobante).
            if (preg_match('/<ds:DigestValue>(.*?)<\/ds:DigestValue>/', $xml, $m)) {
                $hash = $m[1];
            }
        }

        if (method_exists($result, 'isSuccess') && $result->isSuccess()) {
            $cdr = $result->getCdrResponse();
            $code = (string) $cdr->getCode();
            $desc = (string) $cdr->getDescription();
            $notes = method_exists($cdr, 'getNotes') ? (array) $cdr->getNotes() : [];

            $estado = 'aceptado';
            if ($code !== '0' && $code !== '') {
                $estado = 'observado';
            } elseif (! empty($notes)) {
                $estado = 'observado';
                $desc = $desc . ' | ' . implode(' | ', $notes);
            }

            $cdrZip = method_exists($result, 'getCdrZip') ? $result->getCdrZip() : null;

            return ResultadoEmision::ok($estado, $code, $desc ?: 'Comprobante aceptado por SUNAT.', [
                'xml' => $xml,
                'cdr' => $cdrZip,
                'hash' => $hash,
            ]);
        }

        // Error de SUNAT o de conexión.
        $error = method_exists($result, 'getError') ? $result->getError() : null;
        $code = $error ? (string) $error->getCode() : null;
        $msg = $error ? (string) $error->getMessage() : 'SUNAT rechazó el comprobante.';

        $res = ResultadoEmision::error($msg, $code, 'rechazado');
        $res->xml = $xml;
        $res->hash = $hash;

        return $res;
    }

    // ----------------------------------------------------------------
    // Utilidades
    // ----------------------------------------------------------------

    private function disponible(?string &$msg): bool
    {
        if (! class_exists(\Greenter\See::class)) {
            $msg = 'Greenter no está instalado. Ejecuta: composer require greenter/lite';

            return false;
        }
        if (! FacturacionConfig::certificadoExiste()) {
            $msg = 'No se encontró el certificado en: ' . FacturacionConfig::certificadoRuta();

            return false;
        }

        return true;
    }

    /** Convierte un monto a letras en español (parte entera + céntimos). */
    private function numeroALetras(float $numero): string
    {
        $entero = (int) floor($numero);
        $centimos = (int) round(($numero - $entero) * 100);

        $texto = strtoupper($this->enteroALetras($entero));

        return trim($texto) . ' CON ' . str_pad((string) $centimos, 2, '0', STR_PAD_LEFT) . '/100';
    }

    private function enteroALetras(int $n): string
    {
        if ($n === 0) {
            return 'cero';
        }
        if ($n < 0) {
            return 'menos ' . $this->enteroALetras(-$n);
        }

        $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
            'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
            'veinte'];
        $decenas = ['', '', 'veinti', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos',
            'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

        $salida = '';

        if ($n >= 1000000) {
            $millones = intdiv($n, 1000000);
            $salida .= ($millones === 1 ? 'un millón' : $this->enteroALetras($millones) . ' millones') . ' ';
            $n %= 1000000;
        }

        if ($n >= 1000) {
            $miles = intdiv($n, 1000);
            $salida .= ($miles === 1 ? 'mil' : $this->enteroALetras($miles) . ' mil') . ' ';
            $n %= 1000;
        }

        if ($n >= 100) {
            if ($n === 100) {
                $salida .= 'cien ';
                $n = 0;
            } else {
                $salida .= $centenas[intdiv($n, 100)] . ' ';
                $n %= 100;
            }
        }

        if ($n > 0) {
            if ($n <= 20) {
                $salida .= $unidades[$n];
            } elseif ($n < 30) {
                $salida .= 'veinti' . $unidades[$n - 20];
            } else {
                $salida .= $decenas[intdiv($n, 10)];
                if ($n % 10 > 0) {
                    $salida .= ' y ' . $unidades[$n % 10];
                }
            }
        }

        return trim($salida);
    }
}
