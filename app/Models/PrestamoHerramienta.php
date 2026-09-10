<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Préstamo de una herramienta de inventario a un técnico (spec 002, Phase 12 /
 * D15-D16). Reutiliza la tabla `ot_herramientas`. El vínculo duro es con el
 * técnico (para saber quién no ha devuelto); la OT/tarea es solo contexto y no
 * bloquea el ciclo de la OT.
 *
 *   estado: solicitada → entregada → devuelta   (o solicitada → rechazada)
 */
class PrestamoHerramienta extends Model
{
    /** @use HasFactory<\Database\Factories\PrestamoHerramientaFactory> */
    use HasFactory;

    protected $table = 'ot_herramientas';

    protected $fillable = [
        'ot_id',
        'tecnico_id',
        'detalle_ot_id',
        'inventario_id',
        'estado',
        'solicitada_en',
        'movimiento_salida_id',
        'entregada_por',
        'devuelta_en',
        'estado_devolucion',
        'movimiento_devolucion_id',
        'recibida_por',
        'motivo_rechazo',
    ];

    protected function casts(): array
    {
        return [
            'solicitada_en' => 'datetime',
            'devuelta_en' => 'datetime',
        ];
    }

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(Inventario::class, 'inventario_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Tecnico::class, 'tecnico_id');
    }

    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'ot_id');
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(DetalleOt::class, 'detalle_ot_id');
    }

    public function entregadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregada_por');
    }

    public function recibidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibida_por');
    }

    public function estaDevuelto(): bool
    {
        return $this->estado === 'devuelta';
    }

    /** Préstamos activos (entregados y sin devolver) de un técnico. */
    public function scopePendientesDe(Builder $query, int $tecnicoId): Builder
    {
        return $query->where('tecnico_id', $tecnicoId)->where('estado', 'entregada');
    }

    /** Préstamos que un técnico tiene en su poder (cualquier OT). */
    public function scopeSinDevolver(Builder $query): Builder
    {
        return $query->where('estado', 'entregada');
    }

    /** Solicitudes que esperan acción de Bodega. */
    public function scopeEnColaDeBodega(Builder $query): Builder
    {
        return $query->where('estado', 'solicitada');
    }
}
