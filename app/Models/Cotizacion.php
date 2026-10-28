<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cotizacion extends Model
{
    use HasFactory;

    protected $table = 'cotizaciones';

    protected $fillable = [
        'numero',
        'sede',
        'cliente_id',
        'equipo_id',
        'fecha_promesa_entrega',
        'estado',
        'correo_original_referencia',
        'descuento_porcentaje',
        'subtotal',
        'descuento_valor',
        'iva_porcentaje',
        'iva_valor',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'fecha_promesa_entrega' => 'date',
            'descuento_porcentaje' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'descuento_valor' => 'decimal:2',
            'iva_porcentaje' => 'decimal:2',
            'iva_valor' => 'decimal:2',
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

    public function solicitudDespacho(): HasOne
    {
        return $this->hasOne(SolicitudDespacho::class, 'cotizacion_id');
    }

    public function recalcularTotal(): void
    {
        $subtotal = round((float) $this->detalles()->sum('valor_total'), 2);
        $descuento = round($subtotal * ((float) $this->descuento_porcentaje) / 100, 2);
        $base = $subtotal - $descuento;
        $iva = round($base * ((float) $this->iva_porcentaje) / 100, 2);

        $this->update([
            'subtotal' => $subtotal,
            'descuento_valor' => $descuento,
            'iva_valor' => $iva,
            'total' => round($base + $iva, 2),
        ]);
    }
}
