<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Remisión de entrega (spec 003, US6, FR-023/FR-024): documento 1:1 con la
 * solicitud de despacho, con consecutivo propio `REM-#####` y la firma digital
 * del receptor (PNG base64 capturado en pantalla) embebida.
 */
class RemisionEntrega extends Model
{
    /** @use HasFactory<\Database\Factories\RemisionEntregaFactory> */
    use HasFactory;

    protected $table = 'remisiones_entrega';

    protected $fillable = [
        'numero',
        'solicitud_id',
        'generada_por',
        'entregado_por_nombre',
        'fecha',
        'recibido_por_nombre',
        'recibido_por_documento',
        'firma',
        'firma_entrega',
        'nota_entrega',
        'entregada_en',
        'enviada_al_cliente_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'entregada_en' => 'datetime',
            'enviada_al_cliente_en' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudDespacho::class, 'solicitud_id');
    }

    public function generadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generada_por');
    }
}
