<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud de insumo generada por una tarea de OT hacia Bodega (spec 002
 * FR-003; la atiende el Almacenista en spec 003 US1). El descuento de stock
 * ocurre al entregarla (spec 003), enlazándose a su `movimiento_id`.
 */
class SolicitudInsumoOt extends Model
{
    /** @use HasFactory<\Database\Factories\SolicitudInsumoOtFactory> */
    use HasFactory;

    protected $table = 'solicitudes_insumo_ot';

    protected $fillable = [
        'ot_id',
        'detalle_ot_id',
        'detalle_ot_insumo_id',
        'inventario_id',
        'cantidad',
        'estado',
        'solicitada_por',
        'movimiento_id',
        'motivo_rechazo',
    ];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:2'];
    }

    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'ot_id');
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(DetalleOt::class, 'detalle_ot_id');
    }

    public function lineaInsumo(): BelongsTo
    {
        return $this->belongsTo(DetalleOtInsumo::class, 'detalle_ot_insumo_id');
    }

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(Inventario::class, 'inventario_id');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_id');
    }

    /** Solicitudes que aún comprometen stock (pendientes de despacho por Bodega). */
    public function scopeComprometidas(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('estado', ['pendiente', 'aprobada']);
    }

    public function scopePendientesDe(\Illuminate\Database\Eloquent\Builder $query, int $inventarioId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->comprometidas()->where('inventario_id', $inventarioId);
    }
}
