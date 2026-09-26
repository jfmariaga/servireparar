<?php

namespace App\Services\Compras;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Inventario;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Flujo de compra a proveedor (spec 006, US4): recepción → cotización →
 * aprobación → facturación, cada transición con fecha y responsable.
 */
class CompraService
{
    /**
     * @param  array<int, array{inventario_id: int, cantidad: float, costo_unitario: float}>  $items
     */
    public function crear(Proveedor $proveedor, User $creadoPor, array $items, ?string $observaciones = null): Compra
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Agrega al menos un ítem a la solicitud de compra.']);
        }

        return DB::transaction(function () use ($proveedor, $creadoPor, $items, $observaciones) {
            $compra = Compra::create([
                'numero' => (new ConsecutivoCompraService())->siguiente(),
                'proveedor_id' => $proveedor->id,
                'estado' => 'recepcion',
                'observaciones' => $observaciones,
                'creado_por' => $creadoPor->id,
            ]);

            foreach ($items as $item) {
                Inventario::findOrFail($item['inventario_id']);

                DetalleCompra::create([
                    'compra_id' => $compra->id,
                    'inventario_id' => $item['inventario_id'],
                    'cantidad' => $item['cantidad'],
                    'costo_unitario' => $item['costo_unitario'],
                    'valor_total' => round($item['cantidad'] * $item['costo_unitario'], 2),
                ]);
            }

            $compra->recalcularTotal();

            return $compra->fresh(['detalles']);
        });
    }

    public function marcarCotizacion(Compra $compra): Compra
    {
        $this->exigirEstado($compra, 'recepcion');
        $compra->update(['estado' => 'cotizacion', 'cotizada_en' => now()]);

        return $compra->fresh();
    }

    public function aprobar(Compra $compra): Compra
    {
        $this->exigirEstado($compra, 'cotizacion');
        $compra->update(['estado' => 'aprobacion', 'aprobada_en' => now()]);

        return $compra->fresh();
    }

    public function facturar(Compra $compra): Compra
    {
        $this->exigirEstado($compra, 'aprobacion');
        $compra->update(['estado' => 'facturada', 'facturada_en' => now()]);

        return $compra->fresh();
    }

    private function exigirEstado(Compra $compra, string $estado): void
    {
        if ($compra->estado !== $estado) {
            throw ValidationException::withMessages(['estado' => "La compra debe estar en «{$estado}» para esta transición."]);
        }
    }
}
