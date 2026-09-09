<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tarea de una OT (spec 002, `DETALLE_OT`). El costo de mano de obra propia
 * de la tarea = `dias_trabajados × Tecnico::valorDia(fecha de referencia)`
 * (spec 004); no se persiste tarifa aquí. Los insumos requeridos viven en
 * `detalle_ot_insumos` (Phase 11 / D6): una tarea puede tener N líneas de insumo.
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
        'estado_tarea',
        'fecha_inicio',
        'fecha_fin',
        'finalizacion_solicitada_en',
        'dias_trabajados',
    ];

    protected function casts(): array
    {
        return [
            'dias_trabajados' => 'decimal:2',
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
            'finalizacion_solicitada_en' => 'datetime',
        ];
    }

    /** El técnico marcó la tarea lista para finalizar y espera la confirmación del Jefe (D3). */
    public function finalizacionPendiente(): bool
    {
        return $this->finalizacion_solicitada_en !== null && $this->estado_tarea !== 'finalizada';
    }

    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'ot_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Tecnico::class);
    }

    /** Líneas de insumo de la tarea (Phase 11 / D6). */
    public function insumos(): HasMany
    {
        return $this->hasMany(DetalleOtInsumo::class, 'detalle_ot_id');
    }

    /** Solicitudes hacia Bodega generadas por las líneas de insumo de la tarea. */
    public function solicitudesInsumo(): HasMany
    {
        return $this->hasMany(SolicitudInsumoOt::class, 'detalle_ot_id');
    }

    public function tieneInsumos(): bool
    {
        $lineas = $this->relationLoaded('insumos') ? $this->insumos : $this->insumos()->get();

        return $lineas->contains(fn (DetalleOtInsumo $l) => (float) $l->cantidad > 0);
    }

    /**
     * ¿Quedan líneas de insumo sin resolver por Bodega? Cuenta como sin resolver
     * todo lo que NO esté `entregada` ni `cancelada` (es decir `pendiente` o
     * `rechazada`). H4/D3: si es así, la finalización de la tarea la confirma el
     * Jefe de Taller, no el técnico solo.
     */
    public function insumosPendientesDeEntrega(): bool
    {
        $this->loadMissing('solicitudesInsumo');

        return $this->solicitudesInsumo
            ->whereNotIn('estado', ['entregada', 'cancelada'])
            ->isNotEmpty();
    }
}
