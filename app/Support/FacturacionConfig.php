<?php

namespace App\Support;

use App\Models\Configuracion;

/**
 * FacturacionConfig
 * -----------------
 * Lee y guarda la configuración de Facturación Electrónica (Perú · SUNAT)
 * reutilizando la tabla clave-valor `configuraciones` con el prefijo `fe_`.
 *
 * Así no se crea infraestructura nueva de settings: encaja con el modelo
 * Configuracion existente (valor/guardar/todas).
 */
class FacturacionConfig
{
    public const PREFIJO = 'fe_';

    /** Valores por defecto, orientados a BETA (homologación) de SUNAT. */
    public static function defaults(): array
    {
        return [
            'fe_habilitado'        => '0',      // 1 = emite ante SUNAT
            'fe_auto_emitir'       => '1',      // emite al cerrar la venta
            'fe_driver'            => 'null',   // null | greenter
            'fe_entorno'           => 'beta',   // beta | produccion

            // Datos del emisor
            'fe_ruc'               => '20000000001',
            'fe_razon_social'      => 'MINIMARKET DEMO S.A.C.',
            'fe_nombre_comercial'  => 'Mi Botica',
            'fe_direccion'         => 'Av. Principal 123',
            'fe_ubigeo'            => '150101',
            'fe_departamento'      => 'LIMA',
            'fe_provincia'         => 'LIMA',
            'fe_distrito'          => 'LIMA',

            // Credenciales SUNAT
            'fe_sol_usuario'       => 'MODDATOS',
            'fe_sol_clave'         => 'MODDATOS',
            'fe_certificado_ruta'  => storage_path('facturacion/pe/certificate.pem'),

            // Series por tipo de comprobante
            'fe_serie_factura'     => 'F001',
            'fe_serie_boleta'      => 'B001',
            'fe_serie_nota'        => 'BC01',
        ];
    }

    /** Devuelve todas las claves fe_ (defaults + guardadas). */
    public static function todas(): array
    {
        $out = [];
        foreach (self::defaults() as $clave => $porDefecto) {
            $out[$clave] = Configuracion::valor($clave, $porDefecto);
        }

        return $out;
    }

    public static function get(string $clave, mixed $default = null): mixed
    {
        $defaults = self::defaults();

        return Configuracion::valor($clave, $default ?? ($defaults[$clave] ?? null));
    }

    public static function guardar(array $datos): void
    {
        // Solo persistimos claves conocidas del módulo.
        $permitidas = array_keys(self::defaults());
        $filtrado = array_intersect_key($datos, array_flip($permitidas));
        Configuracion::guardar($filtrado);
    }

    // ---- Accesos tipados / estado ----

    public static function habilitado(): bool
    {
        return (string) self::get('fe_habilitado') === '1';
    }

    public static function autoEmitir(): bool
    {
        return (string) self::get('fe_auto_emitir') === '1';
    }

    public static function driver(): string
    {
        return (string) self::get('fe_driver', 'null');
    }

    public static function entorno(): string
    {
        return (string) self::get('fe_entorno', 'beta');
    }

    public static function esBeta(): bool
    {
        return self::entorno() !== 'produccion';
    }

    public static function certificadoRuta(): string
    {
        return (string) self::get('fe_certificado_ruta');
    }

    public static function certificadoExiste(): bool
    {
        $ruta = self::certificadoRuta();

        return $ruta !== '' && is_file($ruta) && is_readable($ruta);
    }

    /** Serie configurada para un tipo de comprobante SUNAT ('01','03','07'). */
    public static function serie(string $tipo): string
    {
        return match ($tipo) {
            '01' => (string) self::get('fe_serie_factura', 'F001'),
            '07' => (string) self::get('fe_serie_nota', 'BC01'),
            default => (string) self::get('fe_serie_boleta', 'B001'),
        };
    }

    /**
     * Resumen para las "chips" de estado del encabezado (como en las imágenes):
     * Habilitada/Deshabilitada · Driver · Modo · Certificado.
     */
    public static function resumen(): array
    {
        return [
            'habilitado'   => self::habilitado(),
            'driver'       => self::driver(),
            'entorno'      => self::entorno(),
            'cert_existe'  => self::certificadoExiste(),
            'greenter'     => class_exists(\Greenter\See::class),
        ];
    }
}
