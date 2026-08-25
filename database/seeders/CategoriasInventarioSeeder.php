<?php

namespace Database\Seeders;

use App\Models\CategoriaInventario;
use Illuminate\Database\Seeder;

class CategoriasInventarioSeeder extends Seeder
{
    /**
     * Siembra las 7 categorías reales del taller (Excel de inventario), mapeadas a
     * los 4 prefijos base de código interno (spec 003, FR-011). El mapeo no está
     * definido de forma exhaustiva en la spec ("no son exhaustivos del Excel real"),
     * así que se agrupan por afinidad: piezas/materiales de reemplazo → REP-,
     * herramientas reutilizables → HER-, consumibles de uso único → CON-.
     * ACC- queda disponible para categorías futuras que no encajen en las anteriores.
     */
    public function run(): void
    {
        $categorias = [
            'Repuestos' => 'REP-',
            'Llantas' => 'REP-',
            'Tuberías y Láminas' => 'REP-',
            'Herramientas' => 'HER-',
            'Insumos' => 'CON-',
            'EPP' => 'CON-',
            'Pinturas' => 'CON-',
        ];

        foreach ($categorias as $nombre => $prefijo) {
            CategoriaInventario::firstOrCreate(['nombre' => $nombre], ['prefijo_codigo' => $prefijo]);
        }
    }
}
