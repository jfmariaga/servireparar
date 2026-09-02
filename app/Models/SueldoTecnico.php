<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un registro del histórico de sueldos de un técnico (spec 004, FR-009). Nunca
 * se edita ni se borra: cada cambio de sueldo agrega una fila nueva.
 */
class SueldoTecnico extends Model
{
    /** @use HasFactory<\Database\Factories\SueldoTecnicoFactory> */
    use HasFactory;

    protected $table = 'sueldos_tecnico';

    protected $fillable = [
        'tecnico_id',
        'sueldo',
        'vigente_desde',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'sueldo' => 'decimal:2',
            'vigente_desde' => 'date',
        ];
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Tecnico::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
