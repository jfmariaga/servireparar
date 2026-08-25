<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AjusteAuditoria extends Model
{
    /** @use HasFactory<\Database\Factories\AjusteAuditoriaFactory> */
    use HasFactory;

    protected $table = 'ajustes_auditoria';

    protected $fillable = [
        'auditoria_id',
        'inventario_id',
        'stock_sistema',
        'stock_fisico',
        'motivo',
        'estado',
        'aprobado_por',
        'resuelto_en',
    ];

    protected function casts(): array
    {
        return [
            'stock_sistema' => 'decimal:2',
            'stock_fisico' => 'decimal:2',
            'resuelto_en' => 'datetime',
        ];
    }

    public function auditoria(): BelongsTo
    {
        return $this->belongsTo(AuditoriaInventario::class, 'auditoria_id');
    }

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(Inventario::class, 'inventario_id');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function diferencia(): float
    {
        return (float) $this->stock_fisico - (float) $this->stock_sistema;
    }
}
