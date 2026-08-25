<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MantenimientoPreventivo extends Model
{
    /** @use HasFactory<\Database\Factories\MantenimientoPreventivoFactory> */
    use HasFactory;

    protected $table = 'mantenimientos_preventivos';

    protected $fillable = [
        'equipo_id',
        'ultima_fecha',
        'proxima_fecha',
        'alerta_disparada',
    ];

    protected function casts(): array
    {
        return [
            'ultima_fecha' => 'date',
            'proxima_fecha' => 'date',
            'alerta_disparada' => 'boolean',
        ];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    /**
     * Próximos a vencer y aún no notificados (spec 005, FR-006).
     */
    public function scopeProximosAVencer(Builder $query, int $diasAntelacion = 7): Builder
    {
        return $query->where('alerta_disparada', false)
            ->whereDate('proxima_fecha', '<=', now()->addDays($diasAntelacion));
    }
}
