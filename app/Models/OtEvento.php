<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrada de bitácora de una OT (spec 002, constitución principio IV).
 * Append-only: sin `updated_at`, nunca se edita.
 */
class OtEvento extends Model
{
    protected $table = 'ot_eventos';

    public $timestamps = false;

    protected $fillable = ['ot_id', 'usuario_id', 'tipo', 'descripcion', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'ot_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
