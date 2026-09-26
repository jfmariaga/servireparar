<?php

namespace App\Services\Cotizaciones;

use App\Contracts\ProveedorCorreoSaliente;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\Inventario;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Construcción, guardado y envío de una Cotización (spec 006, US2). Los
 * ítems pueden ser de la maestra `servicios` o de `inventario` (spec 003),
 * nunca ambos en la misma línea.
 */
class CotizacionService
{
    public function __construct(private readonly ProveedorCorreoSaliente $correoSaliente) {}

    /**
     * @param  array<int, array{tipo_item: string, servicio_id?: int|null, inventario_id?: int|null, cantidad: float}>  $items
     */
    public function guardarItems(Cotizacion $cotizacion, array $items): Cotizacion
    {
        DB::transaction(function () use ($cotizacion, $items) {
            $cotizacion->detalles()->delete();

            foreach ($items as $item) {
                $costoUnitario = $item['tipo_item'] === 'servicio'
                    ? (float) Servicio::findOrFail($item['servicio_id'])->costo_unitario
                    : (float) Inventario::findOrFail($item['inventario_id'])->costo_unitario;

                DetalleCotizacion::create([
                    'cotizacion_id' => $cotizacion->id,
                    'tipo_item' => $item['tipo_item'],
                    'servicio_id' => $item['tipo_item'] === 'servicio' ? $item['servicio_id'] : null,
                    'inventario_id' => $item['tipo_item'] === 'insumo' ? $item['inventario_id'] : null,
                    'cantidad' => $item['cantidad'],
                    'costo_unitario' => $costoUnitario,
                    'valor_total' => round($item['cantidad'] * $costoUnitario, 2),
                ]);
            }

            $cotizacion->recalcularTotal();
        });

        return $cotizacion->fresh(['detalles']);
    }

    public function enviar(Cotizacion $cotizacion, User $actor): Cotizacion
    {
        $cotizacion->loadMissing('cliente', 'detalles');

        if ($cotizacion->detalles->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Agrega al menos un ítem antes de enviar la cotización.']);
        }

        $destinatario = $cotizacion->cliente?->correo;

        if (! $destinatario) {
            throw ValidationException::withMessages(['cliente' => 'Asigna un cliente con correo antes de enviar la cotización.']);
        }

        $messageId = $this->correoSaliente->enviar($cotizacion, $destinatario);

        DB::transaction(function () use ($cotizacion, $actor, $messageId) {
            $cotizacion->update(['estado' => 'cotizada']);

            $cotizacion->mensajes()->create([
                'autor_tipo' => 'administrador',
                'autor_id' => $actor->id,
                'contenido' => 'Cotización enviada al cliente.',
                'message_id_correo' => $messageId,
            ]);
        });

        return $cotizacion->fresh();
    }

    /**
     * Asigna manualmente el cliente cuando el remitente no matcheó
     * automáticamente al recibir el correo (spec 006, edge case de match por
     * correo exacto).
     */
    public function asignarCliente(Cotizacion $cotizacion, Cliente $cliente): Cotizacion
    {
        $cotizacion->update(['cliente_id' => $cliente->id]);

        return $cotizacion->fresh();
    }

    public function marcarEntregada(Cotizacion $cotizacion): Cotizacion
    {
        if ($cotizacion->estado !== 'aceptada') {
            throw ValidationException::withMessages(['estado' => 'Solo una cotización aceptada puede marcarse como entregada.']);
        }

        $cotizacion->update(['estado' => 'entregada']);
        $this->registrarEventoSistema($cotizacion, 'Cotización marcada como entregada.');

        return $cotizacion->fresh();
    }

    public function marcarFacturada(Cotizacion $cotizacion): Cotizacion
    {
        if ($cotizacion->estado !== 'entregada') {
            throw ValidationException::withMessages(['estado' => 'Solo una cotización entregada puede marcarse como facturada.']);
        }

        $cotizacion->update(['estado' => 'facturada']);
        $this->registrarEventoSistema($cotizacion, 'Cotización marcada como facturada.');

        return $cotizacion->fresh();
    }

    private function registrarEventoSistema(Cotizacion $cotizacion, string $contenido): void
    {
        $cotizacion->mensajes()->create([
            'autor_tipo' => 'sistema',
            'contenido' => $contenido,
        ]);
    }
}
