<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Nivel de prioridad de una OT (spec 002). Catálogo fijo (PrioridadesSeeder).
 */
class Prioridad extends Model
{
    protected $table = 'prioridades';

    protected $fillable = ['nombre', 'nivel'];

    public $timestamps = true;
}
