<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evidencia (imagen/documento) asociada a una OT (spec 002, FR-006/FR-017).
 */
class EvidenciaOt extends Model
{
    /** @use HasFactory<\Database\Factories\EvidenciaOtFactory> */
    use HasFactory;

    protected $table = 'evidencias_ot';

    public $timestamps = true;

    protected $fillable = [
        'ot_id',
        'detalle_ot_id',
        'tipo_registro',
        'tipo_archivo',
        'url_archivo',
        'descripcion',
        'subida_por',
        'fecha_subida',
    ];

    protected function casts(): array
    {
        return ['fecha_subida' => 'datetime'];
    }

    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'ot_id');
    }

    public function subidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subida_por');
    }
}
