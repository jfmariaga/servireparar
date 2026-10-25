<?php

namespace App\Services\Cotizaciones;

use App\Models\Cotizacion;

/**
 * Consecutivo de Cotización por sede: `BAQ-0446`, `CTG-0012`, etc. Cada sede
 * lleva su propio correlativo.
 *
 * Debe invocarse dentro de la transacción que crea o renumera la Cotización;
 * usa `lockForUpdate()` sobre el último número de la sede para evitar colisiones.
 */
class ConsecutivoCotizacionService
{
    public function siguiente(string $sede): string
    {
        $prefijo = $sede.'-';

        $ultimo = Cotizacion::where('numero', 'like', $prefijo.'%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('numero');

        $consecutivo = $ultimo
            ? ((int) substr($ultimo, strlen($prefijo))) + 1
            : 1;

        return $prefijo.str_pad((string) $consecutivo, 4, '0', STR_PAD_LEFT);
    }
}
