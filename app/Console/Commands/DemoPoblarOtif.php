<?php

namespace App\Console\Commands;

use App\Enums\RolPrioridad;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\User;
use App\Services\Cotizaciones\CotizacionService;
use App\Services\Cotizaciones\OtifService;
use App\Services\Inventario\DespachoService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Crea despachos de ejemplo (6 a tiempo/completos, 1 tarde, 1 incompleto, y
 * uno vinculado a una cotización) para poder ver el KPI OTIF funcionando con
 * datos reales, sin tener que esperar a que se acumule actividad real.
 *
 * Escribe en la base de datos configurada en .env (la real), así que exige
 * --force para evitar correrla por accidente.
 */
class DemoPoblarOtif extends Command
{
    protected $signature = 'otif:poblar-demo {--force : Confirma que se quiere escribir en la base de datos configurada}';

    protected $description = 'Crea despachos de ejemplo con distintos escenarios de OTIF (a tiempo/tarde, completo/incompleto)';

    private const FIRMA_DEMO = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function handle(CotizacionService $cotizaciones, DespachoService $despachos): int
    {
        if (! $this->option('force')) {
            $this->error('Esto crea registros reales (clientes, despachos, cotizaciones) en la base de datos configurada en .env. Vuelve a correr el comando con --force para confirmar.');

            return self::FAILURE;
        }

        $vendedor = User::role(RolPrioridad::Vendedor->value)->first();
        $almacen = User::role(RolPrioridad::Almacenista->value)->first();
        $cliente = Cliente::query()->first();
        $items = Inventario::where('activo', true)->where('stock_actual', '>=', 8)->take(2)->get();

        if (! $vendedor || ! $almacen || ! $cliente || $items->count() < 2) {
            $this->error('Faltan datos base: se necesita al menos un usuario Vendedor, uno Almacenista, un cliente, y dos insumos de inventario activos con stock >= 8.');

            return self::FAILURE;
        }

        [$itemA, $itemB] = $items;
        $hoy = Carbon::today();
        $creados = [];

        for ($i = 0; $i < 6; $i++) {
            $sol = $this->hacerDespacho($despachos, $vendedor, $almacen, $cliente, $itemA, $hoy->format('Y-m-d'), true);
            $creados[] = $sol->numero.' — a tiempo, completo';
        }

        $sol = $this->hacerDespacho($despachos, $vendedor, $almacen, $cliente, $itemB, $hoy->copy()->subDays(3)->format('Y-m-d'), true);
        $creados[] = $sol->numero.' — TARDE (prometida hace 3 días), completo';

        $sol = $this->hacerDespacho($despachos, $vendedor, $almacen, $cliente, $itemB, $hoy->format('Y-m-d'), false);
        $creados[] = $sol->numero.' — a tiempo, INCOMPLETO';

        $cot = $cotizaciones->crear($vendedor);
        $cot->update(['cliente_id' => $cliente->id]);
        $cotizaciones->guardarCabecera($cot, [
            'sede' => $cot->sede,
            'descuento_porcentaje' => 0,
            'fecha_promesa_entrega' => $hoy->format('Y-m-d'),
        ]);
        $cotizaciones->guardarItems($cot, [
            ['tipo_item' => 'insumo', 'inventario_id' => $itemA->id, 'origen' => 'inventario', 'cantidad' => 1],
        ]);
        $cot->update(['estado' => 'aceptada']);
        $solCot = $cotizaciones->solicitarDespacho($cot->fresh(), $vendedor, $despachos);
        $despachos->recibir($solCot, $almacen);
        $despachos->generarRemision($solCot, $almacen);
        $despachos->confirmarEntrega($solCot, $almacen, 'Recibe Demo', '999999', '', 'Entrega Demo', 'Demo OTIF', self::FIRMA_DEMO, true);
        $creados[] = $solCot->fresh()->numero.' (cotización '.$cot->numero.') — a tiempo, completo';

        $this->info('Despachos creados:');
        foreach ($creados as $linea) {
            $this->line(' - '.$linea);
        }

        $resumen = app(OtifService::class)->calcular();
        $this->newLine();
        $this->info('Resumen OTIF:');
        $this->table(array_keys($resumen), [array_values($resumen)]);

        return self::SUCCESS;
    }

    private function hacerDespacho(DespachoService $despachos, User $vendedor, User $almacen, Cliente $cliente, Inventario $item, string $fechaPromesa, bool $completa): \App\Models\SolicitudDespacho
    {
        $sol = $despachos->crear($vendedor, $cliente->id, 'Demo OTIF', [
            ['origen' => 'inventario', 'inventario_id' => $item->id, 'descripcion' => '', 'cantidad' => '1', 'proveedor_externo' => '', 'costo_compra_externa' => ''],
        ], null, $fechaPromesa);

        $despachos->recibir($sol, $almacen);
        $despachos->generarRemision($sol, $almacen);
        $despachos->confirmarEntrega($sol, $almacen, 'Recibe Demo', '999999', '', 'Entrega Demo', 'Demo OTIF', self::FIRMA_DEMO, $completa);

        return $sol->fresh();
    }
}
