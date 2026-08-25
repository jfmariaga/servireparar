<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaInventario extends Model
{
    /** @use HasFactory<\Database\Factories\CategoriaInventarioFactory> */
    use HasFactory;

    protected $table = 'categorias_inventario';

    protected $fillable = ['nombre', 'prefijo_codigo', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Inventario::class, 'categoria_id');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
