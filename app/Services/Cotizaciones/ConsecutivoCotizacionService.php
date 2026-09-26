<?php

namespace App\Services\Cotizaciones;

use App\Models\Cotizacion;

/**
 * Consecutivo de Cotización con prefijo `COT-` (spec 006). Numeración global
 * corrida, mismo patrón que `ConsecutivoDespachoService`/`OtNumberGenerator`.
 *
 * Debe invocarse dentro de la transacción que crea la Cotización; usa
 * `lockForUpdate()` sobre el último número para evitar colisiones bajo
 * concurrencia.
 */
class ConsecutivoCotizacionService
{
    public const PREFIJO = 'COT-';

    public function siguiente(): string
    {
        $ultimo = Cotizacion::where('numero', 'like', self::PREFIJO.'%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('numero');

        $consecutivo = $ultimo
            ? ((int) substr($ultimo, strlen(self::PREFIJO))) + 1
            : 1;

        return self::PREFIJO.str_pad((string) $consecutivo, 4, '0', STR_PAD_LEFT);
    }
}
