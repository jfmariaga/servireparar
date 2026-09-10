<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
        'orden',
        'dias_cumplimiento',
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
            'dias_cumplimiento' => 'decimal:2',
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
            'finalizacion_solicitada_en' => 'datetime',
        ];
    }

    /**
     * Fecha desde la que corre el plazo de la tarea (Phase 13 / D17): su
     * `fecha_inicio` si ya se inició; si no, la fecha en que se liberó la OT.
     */
    public function fechaReferenciaPlazo(): ?\Illuminate\Support\Carbon
    {
        if ($this->fecha_inicio) {
            return $this->fecha_inicio;
        }

        return $this->relationLoaded('ordenTrabajo')
            ? $this->ordenTrabajo?->fechaLiberacion()
            : $this->ordenTrabajo()->first()?->fechaLiberacion();
    }

    /** Fecha límite de la tarea según su plazo de cumplimiento, o null si no aplica. */
    public function fechaLimitePlazo(): ?\Illuminate\Support\Carbon
    {
        $ref = $this->fechaReferenciaPlazo();

        return ($ref && $this->dias_cumplimiento !== null)
            ? $ref->copy()->addDays((float) $this->dias_cumplimiento)
            : null;
    }

    /** ¿La tarea está atrasada? No finalizada/cancelada y con el plazo vencido (D17). */
    public function estaAtrasada(): bool
    {
        if (in_array($this->estado_tarea, ['finalizada', 'cancelada'], true)) {
            return false;
        }

        $limite = $this->fechaLimitePlazo();

        return $limite !== null && $limite->isPast();
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

    /**
     * Tareas de la misma OT que deben finalizarse antes de iniciar esta (Phase 12 / D10).
     *
     * @return BelongsToMany<DetalleOt, $this>
     */
    public function prerrequisitos(): BelongsToMany
    {
        return $this->belongsToMany(
            DetalleOt::class,
            'detalle_ot_prerrequisitos',
            'detalle_ot_id',
            'prerrequisito_id',
        )->withTimestamps();
    }

    /**
     * Tareas de la misma OT que dependen de esta (esta es prerrequisito suyo).
     *
     * @return BelongsToMany<DetalleOt, $this>
     */
    public function dependientes(): BelongsToMany
    {
        return $this->belongsToMany(
            DetalleOt::class,
            'detalle_ot_prerrequisitos',
            'prerrequisito_id',
            'detalle_ot_id',
        )->withTimestamps();
    }

    /**
     * Prerrequisitos que aún retienen el inicio de esta tarea: los que no están
     * `finalizada` ni `cancelada` (una tarea cancelada deja de contar, D10).
     *
     * @return Collection<int, DetalleOt>
     */
    public function prerrequisitosPendientes(): Collection
    {
        $previas = $this->relationLoaded('prerrequisitos') ? $this->prerrequisitos : $this->prerrequisitos()->get();

        return $previas->reject(fn (DetalleOt $t) => in_array($t->estado_tarea, ['finalizada', 'cancelada'], true))->values();
    }

    /** Líneas de insumo de la tarea (Phase 11 / D6). */
    public function insumos(): HasMany
    {
        return $this->hasMany(DetalleOtInsumo::class, 'detalle_ot_id');
    }

    /** Evidencias (imágenes/documentos) subidas para esta tarea. */
    public function evidencias(): HasMany
    {
        return $this->hasMany(EvidenciaOt::class, 'detalle_ot_id');
    }

    /** ¿La tarea tiene al menos una imagen de evidencia? (requisito para finalizar, Phase 13). */
    public function tieneEvidenciaImagen(): bool
    {
        $evs = $this->relationLoaded('evidencias') ? $this->evidencias : $this->evidencias()->get();

        return $evs->contains(fn (EvidenciaOt $e) => str_starts_with((string) $e->tipo_archivo, 'image/'));
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
