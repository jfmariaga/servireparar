<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tecnico extends Model
{
    /** @use HasFactory<\Database\Factories\TecnicoFactory> */
    use HasFactory;

    protected $fillable = [
        'usuario_id',
        'especialidad_id',
        'tarifa_hora',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'tarifa_hora' => 'decimal:2',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class);
    }

    /**
     * Técnicos disponibles para asignación de nuevas tareas (spec 004, FR-002).
     * Consumido por el selector de operario de OT (spec 002) una vez integrado.
     */
    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
