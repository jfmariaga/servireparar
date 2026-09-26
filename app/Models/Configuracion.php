<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Umbrales editables por el Administrador sin cambios de código (spec 008,
 * T003-T005). Antes de esta tabla vivían hardcodeados en `config/*.php`;
 * esos valores se mantienen como fallback para instalaciones sin seed.
 */
class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = ['clave', 'valor', 'descripcion'];

    public static function obtener(string $clave, mixed $default = null): mixed
    {
        return static::query()->where('clave', $clave)->value('valor') ?? $default;
    }

    public static function establecer(string $clave, string $valor): void
    {
        static::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }
}
