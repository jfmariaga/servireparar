<?php

namespace App\Services\Inventario;

use App\Events\StockBajo;
use App\Exceptions\StockInsuficienteException;
use App\Models\AjusteAuditoria;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Centraliza toda mutación de stock (spec 003) — ningún componente Livewire
 * descuenta stock directamente. Usa lockForUpdate() dentro de una transacción
 * para evitar sobregiro de stock bajo solicitudes concurrentes (edge case del spec).
 */
class MovimientoService
{
    /**
     * Registra una entrada de inventario. Cada entrada es un lote propio: si se
     * informa `$costoUnitario`, ese lote queda disponible con esa cantidad y ese
     * costo exacto (`cantidad_disponible`), y `salida()` lo consume por FIFO — así
     * el costo de cada consumo (y de spec 002 al costear una OT) es el costo real
     * de lo que efectivamente salió, no un promedio ni el precio de otra compra.
     *
     * El costo del maestro (`Inventario::costo_unitario`) se actualiza solo como
     * referencia rápida del costo de la última entrada — `valorTotal()` NO lo usa,
     * usa la suma real de los lotes disponibles.
     */
    public function entrada(
        Inventario $item,
        float $cantidad,
        User $usuario,
        ?Proveedor $proveedor = null,
        ?float $costoUnitario = null,
        ?string $motivo = null,
        ?string $referencia = null,
    ): MovimientoInventario {
        return DB::transaction(function () use ($item, $cantidad, $usuario, $proveedor, $costoUnitario, $motivo, $referencia) {
            $bloqueado = Inventario::whereKey($item->id)->lockForUpdate()->firstOrFail();

            $bloqueado->stock_actual = (float) $bloqueado->stock_actual + $cantidad;

            if ($costoUnitario !== null) {
                $bloqueado->costo_unitario = $costoUnitario;
            }

            $bloqueado->save();

            return MovimientoInventario::create([
                'inventario_id' => $bloqueado->id,
                'tipo_mov' => 'entrada',
                'cantidad' => $cantidad,
                'cantidad_disponible' => $cantidad,
                'costo_unitario' => $costoUnitario,
                'fecha' => now(),
                'motivo' => $motivo,
                'referencia' => $referencia,
                'usuario_id' => $usuario->id,
                'proveedor_id' => $proveedor?->id,
                'origen' => 'entrada_proveedor',
            ]);
        });
    }

    /**
     * Consume lotes de entrada por FIFO (el más antiguo primero) hasta cubrir
     * `$cantidad`, descontando `cantidad_disponible` de cada lote tocado, y
     * devuelve el costo unitario exacto de lo que se consumió (el promedio
     * ponderado únicamente de los lotes que efectivamente salieron, no de todo
     * el inventario). Si los lotes registrados no alcanzan a cubrir `$cantidad`
     * (datos históricos incompletos), usa el costo de referencia del ítem como
     * respaldo para la porción sin lote.
     */
    private function consumirLotesFifo(Inventario $item, float $cantidad): ?float
    {
        $restante = $cantidad;
        $costoTotal = 0.0;
        $tuvoCosto = false;

        $lotes = MovimientoInventario::where('inventario_id', $item->id)
            ->where('tipo_mov', 'entrada')
            ->where('cantidad_disponible', '>', 0)
            ->orderBy('fecha')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($lotes as $lote) {
            if ($restante <= 0) {
                break;
            }

            $tomar = min((float) $lote->cantidad_disponible, $restante);
            $costoTotal += $tomar * (float) ($lote->costo_unitario ?? 0);
            $restante -= $tomar;
            $tuvoCosto = true;

            $lote->decrement('cantidad_disponible', $tomar);
        }

        if ($restante > 0 && $item->costo_unitario !== null) {
            $costoTotal += $restante * (float) $item->costo_unitario;
            $tuvoCosto = true;
        }

        return $tuvoCosto ? round($costoTotal / $cantidad, 2) : null;
    }

    /**
     * @throws StockInsuficienteException si el consumible no tiene stock suficiente (FR-009)
     */
    public function salida(
        Inventario $item,
        float $cantidad,
        User $usuario,
        string $origen,
        ?Cliente $cliente = null,
        ?string $motivo = null,
        ?string $referencia = null,
    ): MovimientoInventario {
        return DB::transaction(function () use ($item, $cantidad, $usuario, $origen, $cliente, $motivo, $referencia) {
            $bloqueado = Inventario::whereKey($item->id)->lockForUpdate()->firstOrFail();

            $costoSalida = $bloqueado->costo_unitario;

            if ($bloqueado->tipo === 'consumible') {
                if ($bloqueado->stock_actual < $cantidad) {
                    throw new StockInsuficienteException(
                        "Stock insuficiente de {$bloqueado->nombre}: disponible {$bloqueado->stock_actual}, solicitado {$cantidad}."
                    );
                }

                $costoSalida = $this->consumirLotesFifo($bloqueado, $cantidad);

                $bloqueado->decrement('stock_actual', $cantidad);
            } else {
                $bloqueado->update(['estado_herramienta' => 'en_uso']);
            }

            $movimiento = MovimientoInventario::create([
                'inventario_id' => $bloqueado->id,
                'tipo_mov' => 'salida',
                'cantidad' => $cantidad,
                'costo_unitario' => $costoSalida,
                'fecha' => now(),
                'motivo' => $motivo,
                'referencia' => $referencia,
                'usuario_id' => $usuario->id,
                'origen' => $origen,
                'cliente_id' => $cliente?->id,
            ]);

            $fresco = $bloqueado->fresh();
            if ($fresco->stockBajoMinimo()) {
                event(new StockBajo($fresco));
            }

            return $movimiento;
        });
    }

    /**
     * Registra la devolución de una herramienta, fijando su nuevo estado
     * (disponible, dañada, en_mantenimiento) — nunca queda ambigua en "en_uso" (SC-004).
     */
    public function devolucion(
        Inventario $item,
        User $usuario,
        string $nuevoEstado,
        ?string $motivo = null,
        ?string $referencia = null,
    ): MovimientoInventario {
        return DB::transaction(function () use ($item, $usuario, $nuevoEstado, $motivo, $referencia) {
            $bloqueado = Inventario::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $bloqueado->update(['estado_herramienta' => $nuevoEstado]);

            return MovimientoInventario::create([
                'inventario_id' => $bloqueado->id,
                'tipo_mov' => 'devolucion',
                'cantidad' => 1,
                'costo_unitario' => $bloqueado->costo_unitario,
                'fecha' => now(),
                'motivo' => $motivo,
                'referencia' => $referencia,
                'usuario_id' => $usuario->id,
                'origen' => 'devolucion',
            ]);
        });
    }

    /**
     * Aplica un ajuste de auditoría ya aprobado por el Administrador al stock_actual
     * (spec 003, FR-008a) — el stock nunca cambia antes de esta aprobación explícita.
     * Para consumibles, además mantiene los lotes consistentes con el nuevo stock:
     * un faltante consume lotes por FIFO igual que una salida; un sobrante crea un
     * lote nuevo (al costo de referencia del ítem, por no tener factura de compra
     * asociada) para que ese stock quede costeable.
     */
    public function aplicarAjusteAprobado(AjusteAuditoria $ajuste, User $administrador): AjusteAuditoria
    {
        return DB::transaction(function () use ($ajuste, $administrador) {
            $item = Inventario::whereKey($ajuste->inventario_id)->lockForUpdate()->firstOrFail();
            $diferencia = round((float) $ajuste->stock_fisico - (float) $item->stock_actual, 2);

            if ($item->tipo === 'consumible' && $diferencia < 0) {
                $this->consumirLotesFifo($item, abs($diferencia));
            } elseif ($item->tipo === 'consumible' && $diferencia > 0) {
                MovimientoInventario::create([
                    'inventario_id' => $item->id,
                    'tipo_mov' => 'entrada',
                    'cantidad' => $diferencia,
                    'cantidad_disponible' => $diferencia,
                    'costo_unitario' => $item->costo_unitario,
                    'fecha' => now(),
                    'motivo' => 'Sobrante detectado en auditoría #'.$ajuste->auditoria_id,
                    'usuario_id' => $administrador->id,
                    'origen' => 'ajuste_auditoria',
                ]);
            }

            $item->update(['stock_actual' => $ajuste->stock_fisico]);

            $ajuste->update([
                'estado' => 'aprobado',
                'aprobado_por' => $administrador->id,
                'resuelto_en' => now(),
            ]);

            return $ajuste;
        });
    }
}
