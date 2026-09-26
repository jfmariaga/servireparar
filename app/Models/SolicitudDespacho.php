<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Solicitud de despacho — canal de venta mostrador sin OT (spec 003, US6).
 * El Vendedor la crea; el Almacenista la hace avanzar
 * `solicitada → recibida → remisionada → entregada`. Anulable en cualquier
 * estado previo a `entregada`. El stock se mueve al confirmar la entrega.
 *
 * Envío con mensajero: desde `remisionada`, `remisionada → despachada →
 * entregada` — el mensajero se lleva la remisión impresa con solo la firma
 * de quien entrega (el stock ya se descuenta en ese punto); al volver con el
 * papel firmado por el cliente se adjunta la evidencia y pasa a `entregada`.
 */
class SolicitudDespacho extends Model
{
    /** @use HasFactory<\Database\Factories\SolicitudDespachoFactory> */
    use HasFactory;

    protected $table = 'solicitudes_despacho';

    protected $fillable = [
        'numero',
        'cliente_id',
        'vendedor_id',
        'sede',
        'estado',
        'observaciones',
        'fecha_solicitud',
        'recibida_por',
        'recibida_en',
        'remisionada_por',
        'remisionada_en',
        'mensajero_nombre',
        'despachada_por',
        'despachada_en',
        'entregada_en',
        'alertado_firma_pendiente_en',
        'anulada_por',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'datetime',
            'recibida_en' => 'datetime',
            'remisionada_en' => 'datetime',
            'despachada_en' => 'datetime',
            'entregada_en' => 'datetime',
            'alertado_firma_pendiente_en' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function despachadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'despachada_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleSolicitudDespacho::class, 'solicitud_id');
    }

    public function detallesInventario(): HasMany
    {
        return $this->detalles()->where('origen', 'inventario');
    }

    public function detallesCompraExterna(): HasMany
    {
        return $this->detalles()->where('origen', 'compra_externa');
    }

    public function remision(): HasOne
    {
        return $this->hasOne(RemisionEntrega::class, 'solicitud_id');
    }

    /**
     * `despachada` ya descontó stock (salió físicamente con el mensajero), así
     * que no es anulable — igual que `entregada`.
     */
    public function puedeAnularse(): bool
    {
        return ! in_array($this->estado, ['despachada', 'entregada', 'anulada'], true);
    }

    public function scopeEnEstado(Builder $query, string $estado): Builder
    {
        return $query->where('estado', $estado);
    }
}
