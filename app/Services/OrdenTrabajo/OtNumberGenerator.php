<?php

namespace App\Services\OrdenTrabajo;

use App\Models\OrdenTrabajo;

/**
 * Consecutivo de OT con prefijo `OTSV-` (spec 002, FR-014), consistente con el
 * formato real del cliente (`OTSV-00001`). Numeración global corrida, sin
 * reinicio anual (mismo criterio que ConsecutivoDespachoService).
 *
 * Debe invocarse dentro de la transacción que crea la OT; usa `lockForUpdate()`
 * sobre el último número para evitar colisiones bajo concurrencia.
 */
class OtNumberGenerator
{
    public const PREFIJO = 'OTSV-';

    public function siguiente(): string
    {
        $ultimo = OrdenTrabajo::where('numero_ot', 'like', self::PREFIJO.'%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('numero_ot');

        $consecutivo = $ultimo
            ? ((int) substr($ultimo, strlen(self::PREFIJO))) + 1
            : 1;

        return self::PREFIJO.str_pad((string) $consecutivo, 5, '0', STR_PAD_LEFT);
    }
}
