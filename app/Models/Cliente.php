<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /** Búsqueda por nombre o NIT (buscador global). */
    public function scopeBuscar($query, ?string $termino)
    {
        if (blank($termino)) {
            return $query;
        }

        return $query->where(fn ($q) => $q
            ->where('nombre', 'like', "%{$termino}%")
            ->orWhere('nit', 'like', "%{$termino}%"));
    }

    /** Clientes que tengan `$correo` entre sus correos registrados (coincidencia exacta de uno). */
    public function scopeConCorreo($query, string $correo)
    {
        return $query->whereRaw('FIND_IN_SET(?, correo)', [trim($correo)]);
    }

    protected function correo(): Attribute
    {
        return Attribute::make(
            set: function (?string $value) {
                $correos = collect(explode(',', (string) $value))
                    ->map(fn ($c) => trim($c))
                    ->filter()
                    ->unique()
                    ->values();

                return $correos->isEmpty() ? null : $correos->implode(',');
            },
        );
    }

    /** @return array<int, string> Lista de correos individuales del cliente. */
    public function correosArray(): array
    {
        return $this->correo ? explode(',', $this->correo) : [];
    }

    public function isActivo(): bool
    {
        return $this->estado === 'activo';
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    public function ordenesTrabajo(): HasMany
    {
        return $this->hasMany(OrdenTrabajo::class);
    }
}
