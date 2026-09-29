<?php

namespace App\Services\Cotizaciones;

use App\Contracts\MensajeCorreoEntrante;
use App\Enums\RolPrioridad;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\MensajeCotizacion;
use App\Notifications\OtNotificacion as CorreoCotizacionNotificacion;
use App\Services\Notificaciones\DestinatariosPorRolService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Traduce un correo entrante ya parseado (`MensajeCorreoEntrante`) en un caso
 * de Cotización (spec 006, US1/US3). Tres caminos posibles:
 *
 * 1. El correo referencia (`In-Reply-To`/`References`) un `message_id_correo`
 *    ya conocido → se agrega al hilo de esa Cotización y se detecta
 *    aceptación/rechazo si estaba "cotizada" esperando respuesta.
 * 2. El correo dice ser una respuesta pero no referencia ningún hilo conocido
 *    (el cliente respondió fuera de hilo, con un correo nuevo) → se notifica
 *    al Administrador para vinculación manual (FR-011), sin crear un caso
 *    nuevo que duplicaría el existente.
 * 3. El correo no es una respuesta: si el asunto sigue el disparador
 *    configurado (`config('cotizaciones.asunto_disparador')` — la "plantilla
 *    esperada" de FR-001, ajustable sin tocar código), se crea un caso nuevo,
 *    con el cliente identificado automáticamente si el remitente coincide
 *    exactamente con `Cliente.correo` (si no, la Cotización queda sin cliente
 *    y el Administrador lo asigna al abrir el caso — el hilo se mantiene de
 *    todas formas por `correo_original_referencia`/`message_id_correo`). Si
 *    no sigue el disparador, se notifica al Administrador en vez de crearlo.
 */
class ProcesarCorreoEntranteService
{
    public function __construct(private readonly DestinatariosPorRolService $destinatarios) {}

    public function procesar(MensajeCorreoEntrante $correo): ?Cotizacion
    {
        if ($correo->esRespuesta()) {
            $mensajeExistente = MensajeCotizacion::whereIn('message_id_correo', $correo->referencias)
                ->with('cotizacion')
                ->first();

            if ($mensajeExistente) {
                return $this->agregarAlHilo($mensajeExistente->cotizacion, $correo);
            }

            $this->notificarAdministrador(
                'Respuesta de cotización fuera de hilo',
                "El correo de {$correo->remitente} responde a una cotización pero no se pudo vincular a ningún caso existente. Asunto: {$correo->asunto}",
            );

            return null;
        }

        if (! $this->sigueLaPlantillaEsperada($correo)) {
            $this->notificarAdministrador(
                'Correo de cotización no reconocido',
                "Llegó un correo de {$correo->remitente} que no sigue el formato esperado de solicitud de cotización. Asunto: {$correo->asunto}",
            );

            return null;
        }

        return $this->crearCaso($correo);
    }

    private function agregarAlHilo(Cotizacion $cotizacion, MensajeCorreoEntrante $correo): Cotizacion
    {
        $cotizacion->mensajes()->create([
            'autor_tipo' => 'cliente',
            'contenido' => $correo->cuerpo,
            'message_id_correo' => $correo->messageId,
        ]);

        $this->detectarRespuesta($cotizacion, $correo->cuerpo);

        return $cotizacion;
    }

    private function detectarRespuesta(Cotizacion $cotizacion, string $cuerpo): void
    {
        if ($cotizacion->estado !== 'cotizada') {
            return;
        }

        $texto = mb_strtolower($cuerpo);
        $acepta = ['acepto', 'aceptamos', 'de acuerdo', 'confirmo', 'confirmamos'];
        $rechaza = ['rechazo', 'rechazamos', 'no acepto', 'declinamos', 'no nos interesa'];

        if ($this->contieneAlguno($texto, $acepta)) {
            $cotizacion->update(['estado' => 'aceptada']);
        } elseif ($this->contieneAlguno($texto, $rechaza)) {
            $cotizacion->update(['estado' => 'rechazada']);
        }
    }

    /** @param  array<int, string>  $frases */
    private function contieneAlguno(string $texto, array $frases): bool
    {
        foreach ($frases as $frase) {
            if (str_contains($texto, $frase)) {
                return true;
            }
        }

        return false;
    }

    private function sigueLaPlantillaEsperada(MensajeCorreoEntrante $correo): bool
    {
        if (trim($correo->remitente) === '' || trim($correo->asunto) === '' || trim($correo->cuerpo) === '') {
            return false;
        }

        $disparador = mb_strtolower((string) config('cotizaciones.asunto_disparador', 'cotiz'));

        return str_contains(mb_strtolower($correo->asunto), $disparador);
    }

    private function crearCaso(MensajeCorreoEntrante $correo): Cotizacion
    {
        return DB::transaction(function () use ($correo) {
            $cliente = Cliente::conCorreo($correo->remitente)->first();

            $cotizacion = Cotizacion::create([
                'numero' => (new ConsecutivoCotizacionService())->siguiente(),
                'cliente_id' => $cliente?->id,
                'estado' => 'en_revision',
                'correo_original_referencia' => $correo->messageId,
            ]);

            $cotizacion->mensajes()->create([
                'autor_tipo' => 'cliente',
                'contenido' => $correo->cuerpo,
                'message_id_correo' => $correo->messageId,
            ]);

            return $cotizacion;
        });
    }

    private function notificarAdministrador(string $titulo, string $cuerpo): void
    {
        $destinatarios = $this->destinatarios->resolver([RolPrioridad::Administrador->value]);

        if ($destinatarios->isEmpty()) {
            return;
        }

        Notification::send($destinatarios, new CorreoCotizacionNotificacion($titulo, $cuerpo, null, 'alerta'));
    }
}
