<?php

namespace App\Services\Inventario;

use App\Models\RemisionEntrega;
use App\Models\SolicitudDespacho;
use Illuminate\Database\Eloquent\Model;

/**
 * Consecutivos del canal de venta sin OT (spec 003, US6): `SD-#####` para la
 * solicitud de despacho y `REM-#####` para la remisión de entrega. Numeración
 * global corrida (no anual), mismo patrón que CodigoInternoService.
 *
 * Debe llamarse dentro de la transacción que crea el registro; usa un lock de
 * lectura sobre el último número para evitar colisiones bajo concurrencia.
 */
class ConsecutivoDespachoService
{
    public function siguienteSolicitud(): string
    {
        return $this->siguiente(SolicitudDespacho::class, 'SD-');
    }

    public function siguienteRemision(): string
    {
        return $this->siguiente(RemisionEntrega::class, 'REM-');
    }

    /**
     * @param  class-string<Model>  $modelo
     */
    private function siguiente(string $modelo, string $prefijo): string
    {
        $ultimo = $modelo::where('numero', 'like', $prefijo.'%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('numero');

        $consecutivo = $ultimo
            ? ((int) substr($ultimo, strlen($prefijo))) + 1
            : 1;

        return $prefijo.str_pad((string) $consecutivo, 5, '0', STR_PAD_LEFT);
    }
}
