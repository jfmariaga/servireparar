<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = [
        'nombre',
        'nit',
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
}
