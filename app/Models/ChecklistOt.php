<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ítem del checklist de cierre de una OT (spec 002, FR-007). `cumple` null =
 * pendiente; el cierre a "Finalizada" se bloquea mientras haya alguno pendiente.
 */
class ChecklistOt extends Model
{
    /** @use HasFactory<\Database\Factories\ChecklistOtFactory> */
    use HasFactory;

    protected $table = 'checklist_ot';

    protected $fillable = ['ot_id', 'item', 'cumple', 'observaciones'];

    protected function casts(): array
    {
        return ['cumple' => 'boolean'];
    }

    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'ot_id');
    }
}
