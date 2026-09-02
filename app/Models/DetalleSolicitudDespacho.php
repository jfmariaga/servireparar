<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de una solicitud de despacho (spec 003, US6). `origen`:
 * - `inventario`: `inventario_id` obligatorio; descuenta stock al entregar y
 *   queda enlazada al `MovimientoInventario` generado (`movimiento_id`).
 * - `compra_externa`: `descripcion` + `proveedor_externo` + `costo_compra_externa`;
 *   NO crea ítem, ni lote, ni movimiento (FR-021).
 */
class DetalleSolicitudDespacho extends Model
{
    /** @use HasFactory<\Database\Factories\DetalleSolicitudDespachoFactory> */
    use HasFactory;

    protected $table = 'detalle_solicitud_despacho';

    public const MOTIVO_COMPRA_EXTERNA = 'No disponible en almacén';

    protected $fillable = [
        'solicitud_id',
        'origen',
        'inventario_id',
        'descripcion',
        'cantidad',
        'costo_unitario',
        'proveedor_externo',
        'costo_compra_externa',
        'motivo',
        'movimiento_id',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'costo_compra_externa' => 'decimal:2',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudDespacho::class, 'solicitud_id');
    }

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(Inventario::class, 'inventario_id');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_id');
    }

    public function esInventario(): bool
    {
        return $this->origen === 'inventario';
    }

    public function esCompraExterna(): bool
    {
        return $this->origen === 'compra_externa';
    }
}
