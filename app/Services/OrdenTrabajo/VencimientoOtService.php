<?php

namespace App\Services\OrdenTrabajo;

use App\Events\OtProximaAVencer;
use App\Models\EstadoOt;
use App\Models\OrdenTrabajo;
use Illuminate\Support\Carbon;

/**
 * Evalúa qué OT abiertas están próximas a vencer o vencidas respecto a su
 * tiempo estimado (spec 002, FR-010) y dispara OtProximaAVencer. El umbral en
 * días sale de `config/ot.php` (ajustable por el Administrador sin tocar código).
 */
class VencimientoOtService
{
    /**
     * @param  bool  $reenviar  vuelve a avisar aunque ya se haya alertado antes (H23: por defecto, una sola vez por OT).
     */
    public function revisar(bool $reenviar = false): int
    {
        $umbral = (int) config('ot.dias_umbral_vencimiento', 2);
        $hoy = Carbon::today();
        $disparadas = 0;

        OrdenTrabajo::query()
            ->whereNotNull('tiempo_estimado_dias')
            ->when(! $reenviar, fn ($q) => $q->whereNull('alertado_vencimiento_en'))
            ->whereHas('estado', fn ($q) => $q->where('es_terminal', false)->whereNot('slug', EstadoOt::FINALIZADA))
            ->with('estado')
            ->chunkById(200, function ($ots) use ($umbral, $hoy, $reenviar, &$disparadas) {
                foreach ($ots as $ot) {
                    $limite = Carbon::parse($ot->created_at)->addDays((float) $ot->tiempo_estimado_dias)->startOfDay();
                    $diasParaLimite = ($limite->getTimestamp() - $hoy->getTimestamp()) / 86400;

                    if ($diasParaLimite <= $umbral) {
                        OtProximaAVencer::dispatch($ot, $diasParaLimite < 0);
                        $ot->forceFill(['alertado_vencimiento_en' => now()])->saveQuietly();
                        $disparadas++;
                    }
                }
            });

        return $disparadas;
    }
}
