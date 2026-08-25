<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditoriaInventario extends Model
{
    /** @use HasFactory<\Database\Factories\AuditoriaInventarioFactory> */
    use HasFactory;

    protected $table = 'auditorias_inventario';

    protected $fillable = ['iniciada_por', 'fecha_inicio', 'fecha_cierre', 'estado'];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime',
            'fecha_cierre' => 'datetime',
        ];
    }

    public function iniciadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iniciada_por');
    }

    public function ajustes(): HasMany
    {
        return $this->hasMany(AjusteAuditoria::class, 'auditoria_id');
    }

    public function estaCerrada(): bool
    {
        return $this->estado !== 'abierta';
    }

    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->where('estado', 'abierta');
    }
}
