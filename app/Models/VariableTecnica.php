<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Variable técnica (nombre, valor, unidad) registrada durante una intervención
 * sobre un equipo (spec 005, FR-004). Esquema clave-valor flexible: no requiere
 * un esquema de base de datos distinto por tipo de equipo.
 */
class VariableTecnica extends Model
{
    /** @use HasFactory<\Database\Factories\VariableTecnicaFactory> */
    use HasFactory;

    protected $table = 'variables_tecnicas';

    protected $fillable = ['ot_id', 'detalle_ot_id', 'nombre', 'valor', 'unidad', 'registrado_por'];

    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'ot_id');
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(DetalleOt::class, 'detalle_ot_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
