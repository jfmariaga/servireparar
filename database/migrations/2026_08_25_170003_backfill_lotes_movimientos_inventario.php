<?php

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reconstruye los lotes (cantidad_disponible) de los movimientos de entrada ya
     * existentes: descuenta FIFO el total histórico de salidas de cada ítem, y si
     * queda stock_actual sin respaldo en ningún lote (ítems importados directamente
     * desde Excel, sin movimiento de entrada propio), crea un lote de "saldo inicial"
     * para que ese stock quede costeable igual que el resto.
     */
    public function up(): void
    {
        $usuarioId = User::query()->value('id');

        Inventario::query()->where('tipo', 'consumible')->orderBy('id')->each(function (Inventario $item) use ($usuarioId) {
            $lotes = MovimientoInventario::where('inventario_id', $item->id)
                ->where('tipo_mov', 'entrada')
                ->orderBy('fecha')
                ->orderBy('id')
                ->get();

            $lotes->each(fn (MovimientoInventario $lote) => $lote->update(['cantidad_disponible' => $lote->cantidad]));

            $consumidoHistorico = (float) MovimientoInventario::where('inventario_id', $item->id)
                ->where('tipo_mov', 'salida')
                ->sum('cantidad');

            foreach ($lotes as $lote) {
                if ($consumidoHistorico <= 0) {
                    break;
                }

                $tomar = min((float) $lote->cantidad_disponible, $consumidoHistorico);
                $lote->decrement('cantidad_disponible', $tomar);
                $consumidoHistorico -= $tomar;
            }

            $disponibleTotal = (float) MovimientoInventario::where('inventario_id', $item->id)
                ->where('tipo_mov', 'entrada')
                ->sum('cantidad_disponible');

            $faltante = round((float) $item->stock_actual - $disponibleTotal, 2);

            if ($faltante > 0 && $usuarioId !== null) {
                MovimientoInventario::create([
                    'inventario_id' => $item->id,
                    'tipo_mov' => 'entrada',
                    'cantidad' => $faltante,
                    'cantidad_disponible' => $faltante,
                    'costo_unitario' => $item->costo_unitario,
                    'fecha' => $item->created_at ?? now(),
                    'motivo' => 'Saldo inicial (migración a costeo por lotes, 2026-08-25)',
                    'usuario_id' => $usuarioId,
                    'origen' => 'ajuste_auditoria',
                ]);
            }
        });
    }

    public function down(): void
    {
        // Irreversible: no se puede reconstruir el estado de los lotes previo al backfill.
    }
};
