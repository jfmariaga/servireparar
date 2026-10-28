<?php

namespace App\Services\Cotizaciones;

use App\Models\SolicitudDespacho;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cálculo del KPI OTIF (On Time In Full), igual a la planilla de referencia
 * del usuario ("Orden perfecta"): %OnTime y %InFull se calculan por separado
 * sobre el universo de despachos entregados con fecha prometida registrada,
 * y %OTIF es el PRODUCTO de las dos tasas (no un AND por pedido).
 *
 * On-Time: `entregada_en - fecha_promesa_entrega <= 0` días (SolicitudDespacho::entregaATiempo()).
 * In-Full: flag manual `entrega_completa` capturado al confirmar la entrega.
 * Las líneas de compra externa cuentan igual que las de inventario: OTIF mide
 * la entrega al cliente como un todo, sin importar el origen de las líneas.
 */
class OtifService
{
    /**
     * @return array{total:int, on_time:int, in_full:int, pct_on_time:?float, pct_in_full:?float, pct_otif:?float, nivel:?string}
     */
    public function calcular(?Carbon $desde = null, ?Carbon $hasta = null): array
    {
        $solicitudes = $this->solicitudesEntregadas($desde, $hasta);

        $total = $solicitudes->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'on_time' => 0,
                'in_full' => 0,
                'pct_on_time' => null,
                'pct_in_full' => null,
                'pct_otif' => null,
                'nivel' => null,
            ];
        }

        $onTime = $solicitudes->filter(fn (SolicitudDespacho $s) => $s->entregaATiempo() === true)->count();
        $inFull = $solicitudes->filter(fn (SolicitudDespacho $s) => $s->entrega_completa === true)->count();

        $pctOnTime = round($onTime / $total * 100, 1);
        $pctInFull = round($inFull / $total * 100, 1);
        $pctOtif = round(($pctOnTime / 100) * ($pctInFull / 100) * 100, 1);

        return [
            'total' => $total,
            'on_time' => $onTime,
            'in_full' => $inFull,
            'pct_on_time' => $pctOnTime,
            'pct_in_full' => $pctInFull,
            'pct_otif' => $pctOtif,
            'nivel' => $this->nivel($pctOtif),
        ];
    }

    /**
     * Detalle "pedido por pedido" para la tabla del reporte, más reciente
     * primero.
     *
     * @return Collection<int, SolicitudDespacho>
     */
    public function detalle(?Carbon $desde = null, ?Carbon $hasta = null): Collection
    {
        return $this->solicitudesEntregadas($desde, $hasta)
            ->load('cotizacion')
            ->sortByDesc('entregada_en')
            ->values();
    }

    private function solicitudesEntregadas(?Carbon $desde, ?Carbon $hasta): Collection
    {
        return SolicitudDespacho::query()
            ->where('estado', 'entregada')
            ->whereNotNull('fecha_promesa_entrega')
            ->whereNotNull('entregada_en')
            ->when($desde, fn ($q) => $q->where('entregada_en', '>=', $desde->copy()->startOfDay()))
            ->when($hasta, fn ($q) => $q->where('entregada_en', '<=', $hasta->copy()->endOfDay()))
            ->get();
    }

    private function nivel(float $pctOtif): string
    {
        return match (true) {
            $pctOtif < 75 => 'plan_choque',
            $pctOtif <= 85 => 'plan_corto_plazo',
            default => 'mantener_mejorar',
        };
    }
}
