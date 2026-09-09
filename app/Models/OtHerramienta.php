<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Herramienta de inventario asignada a una OT (spec 002, Phase 11 / D4). Sale del
 * almacén como `en_uso` al asignarse y vuelve con un estado explícito al devolverse.
 */
class OtHerramienta extends Model
{
    /** @use HasFactory<\Database\Factories\OtHerramientaFactory> */
    use HasFactory;

    protected $table = 'ot_herramientas';

    protected $fillable = [
        'ot_id',
        'inventario_id',
        'asignada_por',
        'asignada_en',
        'movimiento_salida_id',
        'devuelta_en',
        'estado_devolucion',
        'movimiento_devolucion_id',
    ];

    protected function casts(): array
    {
        return [
            'asignada_en' => 'datetime',
            'devuelta_en' => 'datetime',
        ];
    }

    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'ot_id');
    }

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(Inventario::class, 'inventario_id');
    }

    public function asignadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignada_por');
    }

    public function estaDevuelta(): bool
    {
        return $this->devuelta_en !== null;
    }
}
