<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MensajeCotizacion extends Model
{
    use HasFactory;

    protected $table = 'mensajes_cotizacion';

    protected $fillable = [
        'cotizacion_id',
        'autor_tipo',
        'autor_id',
        'contenido',
        'adjunto_url',
        'message_id_correo',
    ];

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }
}
