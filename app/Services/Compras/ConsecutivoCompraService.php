<?php

namespace App\Services\Compras;

use App\Models\Compra;

/**
 * Consecutivo de Compra con prefijo `COM-` (spec 006). Numeración global
 * corrida, mismo patrón que `ConsecutivoDespachoService`/`OtNumberGenerator`.
 */
class ConsecutivoCompraService
{
    public const PREFIJO = 'COM-';

    public function siguiente(): string
    {
        $ultimo = Compra::where('numero', 'like', self::PREFIJO.'%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('numero');

        $consecutivo = $ultimo
            ? ((int) substr($ultimo, strlen(self::PREFIJO))) + 1
            : 1;

        return self::PREFIJO.str_pad((string) $consecutivo, 4, '0', STR_PAD_LEFT);
    }
}
