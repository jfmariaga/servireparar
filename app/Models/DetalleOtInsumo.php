<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Línea de insumo de una tarea de OT (spec 002, Phase 11 / D6). Una tarea puede
 * tener varias; cada línea genera una `SolicitudInsumoOt` hacia Bodega.
 */
class DetalleOtInsumo extends Model
{
    /** @use HasFactory<\Database\Factories\DetalleOtInsumoFactory> */
    use HasFactory;

    protected $table = 'detalle_ot_insumos';

    protected $fillable = [
        'detalle_ot_id',
        'inventario_id',
        'cantidad',
    ];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:2'];
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(DetalleOt::class, 'detalle_ot_id');
    }

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(Inventario::class, 'inventario_id');
    }

    public function solicitud(): HasOne
    {
        return $this->hasOne(SolicitudInsumoOt::class, 'detalle_ot_insumo_id');
    }
}
