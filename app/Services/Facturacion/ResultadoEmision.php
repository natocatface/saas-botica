<?php

namespace App\Services\Facturacion;

/**
 * Resultado normalizado de una operación de emisión / prueba de conexión,
 * independiente del driver (Greenter, Null, etc.).
 */
class ResultadoEmision
{
    public function __construct(
        public bool $ok,
        public string $estado,            // pendiente | aceptado | rechazado | observado | error
        public ?string $codigo = null,    // código CDR de SUNAT
        public ?string $mensaje = null,
        public ?string $xml = null,       // contenido del XML firmado
        public ?string $cdr = null,       // contenido del CDR (zip) devuelto por SUNAT
        public ?string $hash = null,      // DigestValue del XML
        public ?string $ticket = null,
    ) {
    }

    public static function ok(string $estado, ?string $codigo, ?string $mensaje, array $extra = []): self
    {
        return new self(
            ok: true,
            estado: $estado,
            codigo: $codigo,
            mensaje: $mensaje,
            xml: $extra['xml'] ?? null,
            cdr: $extra['cdr'] ?? null,
            hash: $extra['hash'] ?? null,
            ticket: $extra['ticket'] ?? null,
        );
    }

    public static function error(string $mensaje, ?string $codigo = null, string $estado = 'error'): self
    {
        return new self(ok: false, estado: $estado, codigo: $codigo, mensaje: $mensaje);
    }

    public static function pendiente(string $mensaje): self
    {
        return new self(ok: true, estado: 'pendiente', codigo: null, mensaje: $mensaje);
    }
}
