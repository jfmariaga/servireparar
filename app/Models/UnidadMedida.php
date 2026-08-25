<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadMedida extends Model
{
    /** @use HasFactory<\Database\Factories\UnidadMedidaFactory> */
    use HasFactory;

    protected $table = 'unidades_medida';

    protected $fillable = ['nombre', 'abreviatura', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Inventario::class, 'unidad_medida_id');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
