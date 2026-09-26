<?php

namespace App\Services\Inventario;

use App\Enums\RolPrioridad;
use App\Models\Configuracion;
use App\Models\SolicitudDespacho;
use App\Notifications\OtNotificacion as DespachoNotificacion;
use App\Services\Notificaciones\DestinatariosPorRolService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Avisa cuando una solicitud despachada con mensajero lleva demasiados días
 * sin que vuelva el papel firmado por el cliente (umbral editable en
 * `Configuracion`, clave `despachos.dias_alerta_firma_pendiente`; fallback en
 * `config('despachos.dias_alerta_firma_pendiente')`). Espejo de
 * `VencimientoOtService` para OT.
 */
class FirmaFisicaPendienteService
{
    public function __construct(private readonly DestinatariosPorRolService $destinatarios) {}

    /**
     * @param  bool  $reenviar  vuelve a avisar aunque ya se haya alertado antes
     */
    public function revisar(bool $reenviar = false): int
    {
        $umbral = (int) Configuracion::obtener(
            'despachos.dias_alerta_firma_pendiente',
            config('despachos.dias_alerta_firma_pendiente', 3),
        );
        $limite = Carbon::now()->subDays($umbral);
        $disparadas = 0;

        SolicitudDespacho::query()
            ->where('estado', 'despachada')
            ->where('despachada_en', '<=', $limite)
            ->when(! $reenviar, fn ($q) => $q->whereNull('alertado_firma_pendiente_en'))
            ->with('cliente')
            ->chunkById(200, function ($solicitudes) use (&$disparadas) {
                foreach ($solicitudes as $solicitud) {
                    $dias = (int) Carbon::parse($solicitud->despachada_en)->diffInDays(now());

                    Notification::send(
                        $this->destinatarios(),
                        new DespachoNotificacion(
                            'Firma física pendiente de mensajero',
                            "La solicitud {$solicitud->numero} — {$solicitud->cliente?->nombre} salió con ".
                            ($solicitud->mensajero_nombre ?: 'un mensajero')." hace {$dias} día(s) y sigue sin firma física de vuelta.",
                            route('despachos.detalle', $solicitud),
                            'alerta',
                        ),
                    );

                    $solicitud->forceFill(['alertado_firma_pendiente_en' => now()])->saveQuietly();
                    $disparadas++;
                }
            });

        return $disparadas;
    }

    private function destinatarios(): Collection
    {
        return $this->destinatarios->resolver([RolPrioridad::Almacenista->value, RolPrioridad::Vendedor->value]);
    }
}
