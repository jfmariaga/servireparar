<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cotizacion extends Model
{
    use HasFactory;

    protected $table = 'cotizaciones';

    protected $fillable = [
        'numero',
        'cliente_id',
        'equipo_id',
        'estado',
        'correo_original_referencia',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleCotizacion::class);
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(MensajeCotizacion::class)->orderBy('created_at');
    }

    public function recalcularTotal(): void
    {
        $this->update(['total' => $this->detalles()->sum('valor_total')]);
    }
}
