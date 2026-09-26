<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    use HasFactory;

    protected $table = 'compras';

    protected $fillable = [
        'numero',
        'proveedor_id',
        'estado',
        'observaciones',
        'total',
        'creado_por',
        'cotizada_en',
        'aprobada_en',
        'facturada_en',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'cotizada_en' => 'datetime',
            'aprobada_en' => 'datetime',
            'facturada_en' => 'datetime',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleCompra::class);
    }

    public function recalcularTotal(): void
    {
        $this->update(['total' => $this->detalles()->sum('valor_total')]);
    }
}
