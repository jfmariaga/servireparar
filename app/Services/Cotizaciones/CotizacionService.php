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
     * Crea una Cotización en blanco desde la UI (sin correo entrante de por
     * medio). Queda en `en_revision`, sin cliente ni ítems, para completarse
     * en la pantalla de gestión igual que un caso recibido por correo.
     */
    public function crear(User $actor): Cotizacion
    {
        return DB::transaction(function () use ($actor) {
            $sede = config('cotizaciones.sede_por_defecto');

            $cotizacion = Cotizacion::create([
                'numero' => (new ConsecutivoCotizacionService())->siguiente($sede),
                'sede' => $sede,
                'iva_porcentaje' => config('cotizaciones.iva_porcentaje'),
                'estado' => 'en_revision',
            ]);

            $this->registrarEventoSistema($cotizacion, 'Cotización creada manualmente por '.$actor->name.'.');

            return $cotizacion;
        });
    }

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

    /**
     * @param  array{sede: string, descuento_porcentaje: float|string}  $datos
     */
    public function guardarCabecera(Cotizacion $cotizacion, array $datos): Cotizacion
    {
        $this->exigirEstado($cotizacion, ['en_revision'], 'Solo una cotización en revisión puede cambiar de sede o descuento.');

        DB::transaction(function () use ($cotizacion, $datos) {
            if ($datos['sede'] !== $cotizacion->sede) {
                $cotizacion->update([
                    'sede' => $datos['sede'],
                    'numero' => (new ConsecutivoCotizacionService())->siguiente($datos['sede']),
                ]);
            }

            $cotizacion->update(['descuento_porcentaje' => $datos['descuento_porcentaje']]);
            $cotizacion->refresh()->recalcularTotal();
        });

        return $cotizacion->fresh();
    }

    /**
     * Marca manualmente la aceptación cuando el cliente confirmó por un
     * canal distinto al hilo de correo (en persona, teléfono, cotización
     * impresa) — la detección automática (ProcesarCorreoEntranteService)
     * cubre solo las respuestas por correo dentro del hilo.
     */
    public function aceptar(Cotizacion $cotizacion, User $actor): Cotizacion
    {
        $this->exigirEstado($cotizacion, ['cotizada'], 'Solo una cotización enviada puede marcarse como aceptada.');

        DB::transaction(function () use ($cotizacion, $actor) {
            $cotizacion->update(['estado' => 'aceptada']);
            $this->registrarEventoSistema($cotizacion, 'Cotización marcada como aceptada por '.$actor->name.'.');
        });

        return $cotizacion->fresh();
    }

    public function rechazar(Cotizacion $cotizacion, User $actor): Cotizacion
    {
        $this->exigirEstado($cotizacion, ['cotizada'], 'Solo una cotización enviada puede marcarse como rechazada.');

        DB::transaction(function () use ($cotizacion, $actor) {
            $cotizacion->update(['estado' => 'rechazada']);
            $this->registrarEventoSistema($cotizacion, 'Cotización marcada como rechazada por '.$actor->name.'.');
        });

        return $cotizacion->fresh();
    }

    public function reabrir(Cotizacion $cotizacion, User $actor): Cotizacion
    {
        $this->exigirEstado($cotizacion, ['cotizada', 'rechazada'], 'Solo una cotización enviada o rechazada puede reabrirse para editarla.');

        DB::transaction(function () use ($cotizacion, $actor) {
            $cotizacion->update(['estado' => 'en_revision']);
            $this->registrarEventoSistema($cotizacion, 'Cotización reabierta para edición por '.$actor->name.'.');
        });

        return $cotizacion->fresh();
    }

    public function enviar(Cotizacion $cotizacion, User $actor): Cotizacion
    {
        $this->exigirEstado($cotizacion, ['en_revision'], 'Solo una cotización en revisión puede enviarse.');

        $cotizacion->loadMissing('cliente', 'detalles');

        if ($cotizacion->detalles->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Agrega al menos un ítem antes de enviar la cotización.']);
        }

        $destinatarios = $cotizacion->cliente?->correosArray() ?? [];

        if (empty($destinatarios)) {
            throw ValidationException::withMessages(['cliente' => 'Asigna un cliente con correo antes de enviar la cotización.']);
        }

        $messageId = $this->correoSaliente->enviar($cotizacion, $destinatarios);

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
     * Avanza una cotización de `en_revision` a `cotizada` sin enviarla por
     * correo, para cuando se entrega impresa en mano. No exige correo del
     * cliente (a diferencia de enviar()), pero sí un cliente asignado y al
     * menos un ítem, para mantener la trazabilidad de a quién se le cotizó.
     */
    public function marcarCotizadaSinEnvio(Cotizacion $cotizacion, User $actor): Cotizacion
    {
        $this->exigirEstado($cotizacion, ['en_revision'], 'Solo una cotización en revisión puede marcarse como cotizada.');

        $cotizacion->loadMissing('cliente', 'detalles');

        if ($cotizacion->detalles->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Agrega al menos un ítem antes de marcarla como cotizada.']);
        }

        if (! $cotizacion->cliente_id) {
            throw ValidationException::withMessages(['cliente' => 'Asigna un cliente antes de marcarla como cotizada.']);
        }

        DB::transaction(function () use ($cotizacion, $actor) {
            $cotizacion->update(['estado' => 'cotizada']);
            $this->registrarEventoSistema($cotizacion, 'Cotización marcada como cotizada (entregada impresa, sin correo) por '.$actor->name.'.');
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

    /**
     * @param  array<int, string>  $estados
     */
    private function exigirEstado(Cotizacion $cotizacion, array $estados, string $mensaje): void
    {
        if (! in_array($cotizacion->estado, $estados, true)) {
            throw ValidationException::withMessages(['estado' => $mensaje]);
        }
    }

    private function registrarEventoSistema(Cotizacion $cotizacion, string $contenido): void
    {
        $cotizacion->mensajes()->create([
            'autor_tipo' => 'sistema',
            'contenido' => $contenido,
        ]);
    }
}
