<?php

namespace App\Services\Inventario;

use App\Models\CategoriaInventario;
use App\Models\Inventario;

/**
 * Asigna el código interno de un ítem nuevo: prefijo por categoría + consecutivo
 * único (spec 003, FR-011). El consecutivo es por prefijo, no por categoría, ya
 * que varias categorías reales comparten el mismo prefijo base.
 */
class CodigoInternoService
{
    public function generar(CategoriaInventario $categoria): string
    {
        $prefijo = $categoria->prefijo_codigo;

        $ultimoCodigo = Inventario::where('codigo', 'like', "{$prefijo}%")
            ->orderByDesc('id')
            ->value('codigo');

        $consecutivo = $ultimoCodigo
            ? ((int) substr($ultimoCodigo, strlen($prefijo))) + 1
            : 1;

        return $prefijo.str_pad((string) $consecutivo, 5, '0', STR_PAD_LEFT);
    }
}
