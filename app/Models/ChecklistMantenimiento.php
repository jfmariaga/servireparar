<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ítem del checklist técnico digital de una intervención de mantenimiento
 * (spec 005, FR-007). `cumple` null = pendiente de responder, igual que
 * `ChecklistOt`. Se precarga por tarea desde `config('equipos.
 * checklist_tecnico_por_defecto')` al crear una OT con equipo asociado.
 */
class ChecklistMantenimiento extends Model
{
    /** @use HasFactory<\Database\Factories\ChecklistMantenimientoFactory> */
    use HasFactory;

    protected $table = 'checklist_mantenimiento';

    protected $fillable = ['ot_id', 'detalle_ot_id', 'item', 'cumple', 'observaciones', 'respondido_por'];

    protected function casts(): array
    {
        return ['cumple' => 'boolean'];
    }

    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'ot_id');
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(DetalleOt::class, 'detalle_ot_id');
    }

    public function respondidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondido_por');
    }
}
