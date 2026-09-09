<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Estado del ciclo de vida de una OT (spec 002, FR-004). Catálogo fijo
 * (EstadosOtSeeder). Se referencia por `slug` desde EstadoOtService.
 */
class EstadoOt extends Model
{
    public const EN_REVISION = 'en_revision';
    public const PENDIENTE = 'pendiente';
    public const EN_CURSO = 'en_curso';
    public const FINALIZADA = 'finalizada';
    public const ENTREGADA = 'entregada';
    public const CANCELADA = 'cancelada';

    protected $table = 'estados_ot';

    protected $fillable = ['slug', 'nombre', 'orden', 'es_terminal'];

    protected function casts(): array
    {
        return ['es_terminal' => 'boolean'];
    }

    public static function idPorSlug(string $slug): int
    {
        return static::query()->where('slug', $slug)->value('id');
    }
}
