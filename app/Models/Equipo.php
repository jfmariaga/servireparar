<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Equipo extends Model
{
    /** @use HasFactory<\Database\Factories\EquipoFactory> */
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'tipo',
        'marca',
        'modelo',
        'serie',
        'ubicacion',
        'estado',
        'periodicidad_mantenimiento_dias',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function mantenimientoPreventivo(): HasOne
    {
        return $this->hasOne(MantenimientoPreventivo::class);
    }

    public function tienePreventivoProgramado(): bool
    {
        return ! is_null($this->periodicidad_mantenimiento_dias);
    }
}
