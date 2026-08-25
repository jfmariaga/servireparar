<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = [
        'nombre',
        'nit',
        'telefono',
        'correo',
        'direccion',
        'estado',
    ];

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    public function isActivo(): bool
    {
        return $this->estado === 'activo';
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }
}
