<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tarea de una OT (spec 002, `DETALLE_OT`). El costo de mano de obra propia
 * de la tarea = `dias_trabajados × Tecnico::valorDia(fecha de referencia)`
 * (spec 004); no se persiste tarifa aquí.
 */
class DetalleOt extends Model
{
    /** @use HasFactory<\Database\Factories\DetalleOtFactory> */
    use HasFactory;

    protected $table = 'detalle_ot';

    protected $fillable = [
        'ot_id',
        'descripcion',
        'tecnico_id',
        'insumo_id',
        'cantidad_insumo',
        'estado_tarea',
        'fecha_inicio',
        'fecha_fin',
        'dias_trabajados',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_insumo' => 'decimal:2',
            'dias_trabajados' => 'decimal:2',
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
        ];
    }

    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'ot_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Tecnico::class);
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Inventario::class, 'insumo_id');
    }

    public function solicitudInsumo(): HasOne
    {
        return $this->hasOne(SolicitudInsumoOt::class, 'detalle_ot_id');
    }

    public function requiereInsumo(): bool
    {
        return $this->insumo_id !== null && (float) $this->cantidad_insumo > 0;
    }
}
