<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Remisión de entrega (spec 003, US6, FR-023/FR-024): documento 1:1 con la
 * solicitud de despacho, con consecutivo propio `REM-#####`. La entrega en
 * mostrador guarda la firma digital del receptor (`firma`, PNG base64
 * capturado en pantalla); el envío con mensajero guarda en su lugar una foto
 * del papel firmado físicamente (`firma_fisica_foto`), adjuntada cuando el
 * mensajero regresa.
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
        'firma_fisica_foto',
        'firma_fisica_recibida_en',
        'nota_entrega',
        'entregada_en',
        'enviada_al_cliente_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'entregada_en' => 'datetime',
            'firma_fisica_recibida_en' => 'datetime',
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
