<?php

namespace Database\Seeders;

use App\Models\CategoriaInventario;
use App\Models\Inventario;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Inventario\MovimientoService;
use Illuminate\Database\Seeder;

/**
 * Inventario de demostración para poder probar el flujo de OT con insumos
 * (spec 002, Phase 11). Idempotente: se puede re-correr sin duplicar ni volver a
 * sumar stock. Cada consumible entra con un lote real (MovimientoService::entrada)
 * para que el costeo FIFO de la OT tome un costo verdadero.
 */
class InventarioDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@servireparar.com')->first() ?? User::first();
        $movimientos = app(MovimientoService::class);

        $consumibles = [
            // [código, nombre, categoría, unidad, stock, mínimo, costo unitario]
            ['REP-00001', 'Rodamiento 6203 2RS', 'Repuestos', 'Unidad', 5, 4, 18_000],
            ['REP-00002', 'Filtro de aire industrial', 'Repuestos', 'Unidad', 12, 4, 45_000],
            ['CON-00001', 'Grasa multipropósito EP2', 'Insumos', 'Kilogramo', 50, 10, 12_000],
            ['CON-00002', 'Disco de corte 7"', 'Insumos', 'Unidad', 100, 20, 7_000],
            ['CON-00003', 'Electrodo 6011 1/8"', 'Insumos', 'Kilogramo', 40, 10, 9_500],
            ['CON-00004', 'Pintura anticorrosiva gris', 'Pinturas', 'Galón', 8, 3, 85_000],
            ['CON-00005', 'Guantes de carnaza', 'EPP', 'Par', 30, 10, 9_000],
        ];

        foreach ($consumibles as [$codigo, $nombre, $categoria, $unidad, $stock, $minimo, $costo]) {
            $existe = Inventario::where('codigo', $codigo)->exists();

            $item = Inventario::firstOrCreate(['codigo' => $codigo], [
                'nombre' => $nombre,
                'tipo' => 'consumible',
                'categoria_id' => CategoriaInventario::where('nombre', $categoria)->value('id'),
                'unidad_medida_id' => UnidadMedida::where('nombre', $unidad)->value('id'),
                'stock_actual' => 0,
                'stock_minimo' => $minimo,
                'costo_unitario' => $costo,
                'activo' => true,
            ]);

            if (! $existe && $admin) {
                $movimientos->entrada($item, (float) $stock, $admin, costoUnitario: (float) $costo, motivo: 'Carga inicial (demo)');
            }
        }

        $herramientas = [
            ['HER-00001', 'Torquímetro 1/2" 20-210 Nm', 'Herramientas'],
            ['HER-00002', 'Pulidora angular 4-1/2"', 'Herramientas'],
            ['HER-00003', 'Taladro percutor 1/2"', 'Herramientas'],
        ];

        foreach ($herramientas as [$codigo, $nombre, $categoria]) {
            Inventario::firstOrCreate(['codigo' => $codigo], [
                'nombre' => $nombre,
                'tipo' => 'herramienta',
                'categoria_id' => CategoriaInventario::where('nombre', $categoria)->value('id'),
                'unidad_medida_id' => UnidadMedida::where('nombre', 'Unidad')->value('id'),
                'stock_actual' => 1,
                'stock_minimo' => 0,
                'costo_unitario' => null,
                'estado_herramienta' => 'disponible',
                'activo' => true,
            ]);
        }
    }
}
