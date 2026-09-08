<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Participación de un contratista externo en una OT (spec 002, FR-015).
 */
class OtManoObraContratista extends Model
{
    /** @use HasFactory<\Database\Factories\OtManoObraContratistaFactory> */
    use HasFactory;

    protected $table = 'ot_mano_obra_contratista';

    protected $fillable = [
        'ot_id',
        'contratista_id',
        'especialidad',
        'cantidad',
        'valor',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'valor' => 'decimal:2',
        ];
    }

    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'ot_id');
    }

    public function contratista(): BelongsTo
    {
        return $this->belongsTo(Contratista::class);
    }
}
