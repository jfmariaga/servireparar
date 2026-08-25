<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInventario extends Model
{
    /** @use HasFactory<\Database\Factories\MovimientoInventarioFactory> */
    use HasFactory;

    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'inventario_id',
        'tipo_mov',
        'cantidad',
        'cantidad_disponible',
        'costo_unitario',
        'fecha',
        'motivo',
        'referencia',
        'usuario_id',
        'proveedor_id',
        'origen',
        'cliente_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'cantidad' => 'decimal:2',
            'cantidad_disponible' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
        ];
    }

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(Inventario::class, 'inventario_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
