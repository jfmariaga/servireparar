<?php

namespace App\Models;

use App\Support\Moneda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventario extends Model
{
    /** @use HasFactory<\Database\Factories\InventarioFactory> */
    use HasFactory;

    protected $table = 'inventario';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'categoria_id',
        'ubicacion',
        'codigo_barras',
        'unidad_medida_id',
        'stock_actual',
        'stock_minimo',
        'costo_unitario',
        'estado_herramienta',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'stock_actual' => 'decimal:2',
            'stock_minimo' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaInventario::class, 'categoria_id');
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'inventario_id');
    }

    /**
     * Lotes de entrada con saldo sin consumir — fuente real del valor del ítem
     * (ver `valorTotal()`), en vez de un único costo promediado o reemplazado.
     */
    public function lotesDisponibles(): HasMany
    {
        return $this->movimientos()
            ->where('tipo_mov', 'entrada')
            ->where('cantidad_disponible', '>', 0)
            ->orderBy('fecha')
            ->orderBy('id');
    }

    public function esHerramienta(): bool
    {
        return $this->tipo === 'herramienta';
    }

    public function stockBajoMinimo(): bool
    {
        return $this->tipo === 'consumible' && $this->stock_actual < $this->stock_minimo;
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function costoUnitarioFormateado(): string
    {
        return Moneda::cop($this->costo_unitario);
    }

    /**
     * Suma el costo real de cada lote de entrada con saldo disponible — no
     * `stock_actual × costo_unitario`, porque `costo_unitario` es solo una
     * referencia al costo de la última entrada, no el costo real de todo el
     * stock (que puede venir de compras a precios distintos).
     */
    public function valorTotal(): float
    {
        return (float) $this->lotesDisponibles->sum(
            fn (MovimientoInventario $lote) => (float) $lote->cantidad_disponible * (float) ($lote->costo_unitario ?? 0)
        );
    }

    public function valorTotalFormateado(): string
    {
        return Moneda::cop($this->valorTotal());
    }
}
