<?php

namespace App\Services\Facturacion;

use App\Models\ComprobanteElectronico;

/**
 * Contrato de un "driver" de emisión electrónica.
 * Permite intercambiar el motor (Greenter, un PSE externo, o Null) sin tocar
 * el resto del sistema.
 */
interface EmisorInterface
{
    /** Nombre corto del driver (greenter, null, ...). */
    public function nombre(): string;

    /** Envía el comprobante a SUNAT y devuelve el resultado normalizado. */
    public function emitir(ComprobanteElectronico $comprobante): ResultadoEmision;

    /** Emite una nota de crédito que anula/modifica un comprobante previo. */
    public function emitirNotaCredito(ComprobanteElectronico $nota, ComprobanteElectronico $referencia): ResultadoEmision;

    /** Prueba de conectividad/credenciales contra SUNAT. */
    public function probarConexion(): ResultadoEmision;
}
