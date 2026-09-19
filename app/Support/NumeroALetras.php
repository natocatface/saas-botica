<?php

namespace App\Support;

/**
 * Convierte importes a su representación en letras (español), para la
 * representación impresa de comprobantes electrónicos.
 */
class NumeroALetras
{
    /** Ej: 123.45 → "CIENTO VEINTITRÉS CON 45/100" */
    public static function soles(float $numero): string
    {
        $entero = (int) floor($numero);
        $centimos = (int) round(($numero - $entero) * 100);

        return mb_strtoupper(trim(self::entero($entero)))
            . ' CON ' . str_pad((string) $centimos, 2, '0', STR_PAD_LEFT) . '/100';
    }

    private static function entero(int $n): string
    {
        if ($n === 0) {
            return 'cero';
        }
        if ($n < 0) {
            return 'menos ' . self::entero(-$n);
        }

        $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
            'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte'];
        $decenas = ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos',
            'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

        $salida = '';

        if ($n >= 1000000) {
            $millones = intdiv($n, 1000000);
            $salida .= ($millones === 1 ? 'un millón' : self::entero($millones) . ' millones') . ' ';
            $n %= 1000000;
        }

        if ($n >= 1000) {
            $miles = intdiv($n, 1000);
            $salida .= ($miles === 1 ? 'mil' : self::entero($miles) . ' mil') . ' ';
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
