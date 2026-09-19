<?php

namespace App\Services\Facturacion\Drivers;

use App\Models\ComprobanteElectronico;
use App\Services\Facturacion\EmisorInterface;
use App\Services\Facturacion\ResultadoEmision;

/**
 * Driver "Ninguno": no emite ante SUNAT, deja el comprobante en estado
 * pendiente. Es el valor por defecto y permite operar el POS sin envío real.
 */
class NullEmisor implements EmisorInterface
{
    public function nombre(): string
    {
        return 'null';
    }

    public function emitir(ComprobanteElectronico $comprobante): ResultadoEmision
    {
        return ResultadoEmision::pendiente('Driver "Ninguno": comprobante registrado sin envío a SUNAT.');
    }

    public function emitirNotaCredito(ComprobanteElectronico $nota, ComprobanteElectronico $referencia): ResultadoEmision
    {
        return ResultadoEmision::pendiente('Driver "Ninguno": nota de crédito registrada sin envío a SUNAT.');
    }

    public function probarConexion(): ResultadoEmision
    {
        return ResultadoEmision::pendiente('Driver "Ninguno" activo: no se realiza conexión con SUNAT.');
    }
}
