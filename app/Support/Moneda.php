<?php

namespace App\Support;

/**
 * Formato de moneda para pesos colombianos: separador de miles con punto,
 * sin decimales (el COP no maneja centavos en el uso cotidiano del taller).
 */
class Moneda
{
    public static function cop(float|int|string|null $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return '$ '.number_format((float) $valor, 0, ',', '.');
    }
}
