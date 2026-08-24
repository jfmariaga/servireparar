<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contratista extends Model
{
    use HasFactory;

    protected $table = 'contratistas';

    protected $fillable = [
        'nombre',
        'especialidad',
        'telefono',
        'correo',
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
