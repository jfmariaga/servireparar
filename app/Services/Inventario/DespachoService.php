<?php

namespace App\Services\Inventario;

use App\Mail\RemisionEntregada;
use App\Models\DetalleSolicitudDespacho;
use App\Models\Inventario;
use App\Models\RemisionEntrega;
use App\Models\SolicitudDespacho;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Orquesta el canal de venta mostrador sin OT (spec 003, US6): creación de la
 * solicitud de despacho con líneas, transiciones de estado y confirmación de la
 * entrega firmada. El descuento de stock (por línea de inventario, vía
 * MovimientoService con `origen: despacho` y costeo FIFO) ocurre SOLO en
 * `confirmarEntrega()`. Las líneas de compra externa nunca tocan el inventario.
 */
class DespachoService
{
    public function __construct(
        private readonly ConsecutivoDespachoService $consecutivos = new ConsecutivoDespachoService(),
        private readonly MovimientoService $movimientos = new MovimientoService(),
    ) {}

    /**
     * @param  array<int, array{origen:string, inventario_id:?int, descripcion:?string, cantidad:float|string, proveedor_externo:?string, costo_compra_externa:float|string|null}>  $lineas
     */
    public function crear(User $vendedor, int $clienteId, ?string $observaciones, array $lineas, ?string $sede = null): SolicitudDespacho
    {
        if ($lineas === []) {
            throw ValidationException::withMessages(['lineas' => 'Agrega al menos una línea a la solicitud.']);
        }

        $sedes = array_keys(config('despachos.sedes', []));
        $sede = in_array($sede, $sedes, true) ? $sede : config('despachos.sede_por_defecto', 'BAQ');

        return DB::transaction(function () use ($vendedor, $clienteId, $observaciones, $lineas, $sede) {
            $solicitud = SolicitudDespacho::create([
                'numero' => $this->consecutivos->siguienteSolicitud(),
                'cliente_id' => $clienteId,
                'vendedor_id' => $vendedor->id,
                'sede' => $sede,
                'estado' => 'solicitada',
                'observaciones' => $observaciones,
                'fecha_solicitud' => now(),
            ]);

            foreach ($lineas as $linea) {
                $solicitud->detalles()->create($this->normalizarLinea($linea));
            }

            return $solicitud->load('detalles');
        });
    }

    /**
     * @param  array<string, mixed>  $linea
     * @return array<string, mixed>
     */
    private function normalizarLinea(array $linea): array
    {
        $cantidad = (float) $linea['cantidad'];

        if (($linea['origen'] ?? null) === 'compra_externa') {
            return [
                'origen' => 'compra_externa',
                'inventario_id' => null,
                'descripcion' => $linea['descripcion'] ?? null,
                'cantidad' => $cantidad,
                'proveedor_externo' => $linea['proveedor_externo'] ?? null,
                'costo_compra_externa' => $linea['costo_compra_externa'] !== null && $linea['costo_compra_externa'] !== ''
                    ? (float) $linea['costo_compra_externa']
                    : null,
                'motivo' => DetalleSolicitudDespacho::MOTIVO_COMPRA_EXTERNA,
            ];
        }

        $item = Inventario::findOrFail($linea['inventario_id']);

        return [
            'origen' => 'inventario',
            'inventario_id' => $item->id,
            'descripcion' => $item->nombre,
            'cantidad' => $cantidad,
            'costo_unitario' => $item->costo_unitario,
            'motivo' => null,
        ];
    }

    public function recibir(SolicitudDespacho $solicitud, User $almacenista): void
    {
        $this->asegurarEstado($solicitud, 'solicitada');

        $solicitud->update([
            'estado' => 'recibida',
            'recibida_por' => $almacenista->id,
            'recibida_en' => now(),
        ]);
    }

    public function generarRemision(SolicitudDespacho $solicitud, User $almacenista): RemisionEntrega
    {
        $this->asegurarEstado($solicitud, 'recibida');

        return DB::transaction(function () use ($solicitud, $almacenista) {
            $remision = $solicitud->remision()->create([
                'numero' => $this->consecutivos->siguienteRemision(),
                'generada_por' => $almacenista->id,
                'fecha' => now(),
            ]);

            $solicitud->update([
                'estado' => 'remisionada',
                'remisionada_por' => $almacenista->id,
                'remisionada_en' => now(),
            ]);

            return $remision;
        });
    }

    /**
     * Confirma la entrega: exige la firma del receptor (FR-024), genera un
     * movimiento de salida `origen: despacho` por cada línea de inventario
     * (descuento + costeo FIFO) y bloquea toda la operación si alguna línea de
     * inventario no tiene stock suficiente (FR-009 / escenario 7). Las líneas de
     * compra externa no producen efecto en inventario.
     *
     * Recibida a satisfacción, envía una copia del PDF de la remisión al correo
     * del cliente (si tiene uno registrado).
     *
     * @return bool `true` si se envió la copia al correo del cliente
     */
    public function confirmarEntrega(
        SolicitudDespacho $solicitud,
        User $almacenista,
        string $recibidoPorNombre,
        string $recibidoPorDocumento,
        string $firma,
        ?string $entregadoPorNombre = null,
        ?string $notaEntrega = null,
        string $firmaEntrega = '',
    ): bool {
        $this->asegurarEstado($solicitud, 'remisionada');

        if (trim($firma) === '') {
            throw ValidationException::withMessages(['firma' => 'La firma del receptor es obligatoria para confirmar la entrega.']);
        }

        if (trim($firmaEntrega) === '') {
            throw ValidationException::withMessages(['firmaEntrega' => 'La firma de quien entrega es obligatoria para confirmar la entrega.']);
        }

        DB::transaction(function () use ($solicitud, $almacenista, $recibidoPorNombre, $recibidoPorDocumento, $firma, $firmaEntrega, $entregadoPorNombre, $notaEntrega) {
            $solicitud->loadMissing('cliente');

            foreach ($solicitud->detallesInventario()->with('inventario')->get() as $detalle) {
                $movimiento = $this->movimientos->salida(
                    $detalle->inventario,
                    (float) $detalle->cantidad,
                    $almacenista,
                    origen: 'despacho',
                    cliente: $solicitud->cliente,
                    motivo: 'Despacho '.$solicitud->numero.' (venta sin OT)',
                    referencia: $solicitud->remision->numero,
                );

                $detalle->update([
                    'movimiento_id' => $movimiento->id,
                    'costo_unitario' => $movimiento->costo_unitario ?? $detalle->costo_unitario,
                ]);
            }

            $solicitud->remision->update([
                'entregado_por_nombre' => $entregadoPorNombre ?: $almacenista->name,
                'recibido_por_nombre' => $recibidoPorNombre,
                'recibido_por_documento' => $recibidoPorDocumento,
                'firma' => $firma,
                'firma_entrega' => $firmaEntrega,
                'nota_entrega' => $notaEntrega,
                'entregada_en' => now(),
            ]);

            $solicitud->update([
                'estado' => 'entregada',
                'entregada_en' => now(),
            ]);
        });

        return $this->enviarCopiaAlCliente($solicitud->fresh(['cliente', 'vendedor', 'detalles.inventario', 'remision.generadaPor']));
    }

    /**
     * Envía la copia PDF de la remisión al correo del cliente, si tiene uno.
     * Un fallo de correo no revierte la entrega ya confirmada.
     */
    private function enviarCopiaAlCliente(SolicitudDespacho $solicitud): bool
    {
        $correo = $solicitud->cliente->correo;

        if (blank($correo)) {
            return false;
        }

        Mail::to($correo)->send(new RemisionEntregada($solicitud));

        $solicitud->remision->update(['enviada_al_cliente_en' => now()]);

        return true;
    }

    public function anular(SolicitudDespacho $solicitud, User $actor, ?string $motivo = null): void
    {
        if (! $solicitud->puedeAnularse()) {
            throw ValidationException::withMessages([
                'estado' => 'No se puede anular una solicitud en estado "'.$solicitud->estado.'".',
            ]);
        }

        $solicitud->update([
            'estado' => 'anulada',
            'anulada_por' => $actor->id,
            'motivo_anulacion' => $motivo,
        ]);
    }

    private function asegurarEstado(SolicitudDespacho $solicitud, string $esperado): void
    {
        if ($solicitud->estado !== $esperado) {
            throw ValidationException::withMessages([
                'estado' => 'La solicitud debe estar en estado "'.$esperado.'"; está en "'.$solicitud->estado.'".',
            ]);
        }
    }
}
