<?php

namespace App\Console\Commands;

use App\Models\CategoriaInventario;
use App\Models\Inventario;
use App\Models\UnidadMedida;
use App\Services\Inventario\CodigoInternoService;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Importa el Excel real de inventario del taller (INVENTARIO SERVIREPARAR.xlsx) como
 * datos de prueba en la tabla `inventario`, para poblar el sistema con un catálogo
 * realista sin tener que digitarlo a mano.
 *
 * No forma parte del DatabaseSeeder por defecto: es una utilidad puntual de carga de
 * datos de prueba, no un seed reproducible del sistema (el archivo fuente no viaja
 * necesariamente con el proyecto en todos los entornos).
 */
class ImportarInventarioExcel extends Command
{
    protected $signature = 'inventario:importar-excel
        {ruta? : Ruta al archivo .xlsx (por defecto busca en la raíz del proyecto)}
        {--fresco : Elimina los ítems de inventario existentes antes de importar}';

    protected $description = 'Importa el Excel real de inventario del taller como datos de prueba';

    /**
     * Cada hoja del Excel mapea a una categoría real ya sembrada por
     * CategoriasInventarioSeeder. Todas comparten el mismo layout de columnas
     * (fila de encabezado variable, columnas fijas), salvo excepciones anotadas.
     *
     * Columnas: 2=ubicación, 3=nombre, 4=marca (Tuberías: metros por unidad),
     * 5=stock mínimo, 9=saldo (stock actual), 11/12=precio según hoja, 12=medida
     * (cuando aplica), 13=estado de stock (no se usa: es salud de stock, no
     * condición de herramienta — ver spec 003 sesión 2026-08-25 en el código).
     */
    private const HOJAS = [
        'LLANTAS' => ['categoria' => 'Llantas', 'header' => 3, 'precio' => null, 'medida' => 12, 'unidadFija' => null],
        'EPP' => ['categoria' => 'EPP', 'header' => 3, 'precio' => null, 'medida' => 12, 'unidadFija' => null],
        'TUBERIAS Y LAMINAS' => ['categoria' => 'Tuberías y Láminas', 'header' => 3, 'precio' => 12, 'medida' => null, 'unidadFija' => 'Metro'],
        'INSUMOS' => ['categoria' => 'Insumos', 'header' => 2, 'precio' => 12, 'medida' => null, 'unidadFija' => 'Unidad'],
        'PINTURAS' => ['categoria' => 'Pinturas', 'header' => 3, 'precio' => 11, 'medida' => 12, 'unidadFija' => null],
        'HERRAMIENTAS' => ['categoria' => 'Herramientas', 'header' => 3, 'precio' => null, 'medida' => 12, 'unidadFija' => null],
        'REPUESTOS' => ['categoria' => 'Repuestos', 'header' => 2, 'precio' => 11, 'medida' => 12, 'unidadFija' => null],
    ];

    private const MEDIDA_MAP = [
        'GALON' => 'Galón',
        'UNIDAD' => 'Unidad',
        'METRO' => 'Metro',
    ];

    public function handle(CodigoInternoService $codigos): int
    {
        $rutaDefecto = base_path('INVENTARIO  SERVIREPARAR (1).xlsx');
        $ruta = $this->argument('ruta') ?? $rutaDefecto;

        if (! is_file($ruta)) {
            $this->error("No se encontró el archivo: {$ruta}");

            return self::FAILURE;
        }

        if ($this->option('fresco')) {
            Inventario::query()->delete();
            $this->info('Ítems de inventario existentes eliminados (--fresco).');
        }

        $spreadsheet = IOFactory::load($ruta);

        $creados = 0;
        $omitidos = 0;
        $ajustadosNegativos = 0;

        foreach (self::HOJAS as $nombreHoja => $config) {
            if (! $spreadsheet->sheetNameExists($nombreHoja)) {
                $this->warn("Hoja no encontrada, se omite: {$nombreHoja}");

                continue;
            }

            $categoria = CategoriaInventario::where('nombre', $config['categoria'])->first();
            if (! $categoria) {
                $this->warn("Categoría no sembrada, se omite hoja {$nombreHoja}: {$config['categoria']}");

                continue;
            }

            $tipo = $nombreHoja === 'HERRAMIENTAS' ? 'herramienta' : 'consumible';
            $unidadFija = $config['unidadFija'] ? UnidadMedida::where('nombre', $config['unidadFija'])->first() : null;

            $hoja = $spreadsheet->getSheetByName($nombreHoja);
            $maxFila = $hoja->getHighestDataRow();

            for ($fila = $config['header'] + 1; $fila <= $maxFila; $fila++) {
                $nombre = trim((string) $hoja->getCell([3, $fila])->getCalculatedValue());

                if ($nombre === '') {
                    continue;
                }

                $marcaCruda = $hoja->getCell([4, $fila])->getCalculatedValue();
                $marca = ($nombreHoja !== 'TUBERIAS Y LAMINAS' && is_string($marcaCruda) && trim($marcaCruda) !== '')
                    ? trim($marcaCruda)
                    : null;

                $nombreCompleto = $marca ? "{$nombre} ({$marca})" : $nombre;

                if (Inventario::where('categoria_id', $categoria->id)->where('nombre', $nombreCompleto)->exists()) {
                    $omitidos++;

                    continue;
                }

                $stockMinimo = $this->numero($hoja->getCell([5, $fila])->getCalculatedValue());
                $stockActual = $this->numero($hoja->getCell([9, $fila])->getCalculatedValue());

                if ($stockActual < 0) {
                    $stockActual = 0;
                    $ajustadosNegativos++;
                }

                $costoUnitario = $config['precio']
                    ? $this->numeroONulo($hoja->getCell([$config['precio'], $fila])->getCalculatedValue())
                    : null;

                $unidad = $unidadFija;
                if (! $unidad && $config['medida']) {
                    $medidaCruda = strtoupper(trim((string) $hoja->getCell([$config['medida'], $fila])->getCalculatedValue()));
                    $nombreUnidad = self::MEDIDA_MAP[$medidaCruda] ?? null;
                    $unidad = $nombreUnidad ? UnidadMedida::where('nombre', $nombreUnidad)->first() : null;
                }

                $codigo = $codigos->generar($categoria);

                Inventario::create([
                    'codigo' => $codigo,
                    'nombre' => $nombreCompleto,
                    'tipo' => $tipo,
                    'categoria_id' => $categoria->id,
                    'ubicacion' => null,
                    'codigo_barras' => $codigo,
                    'unidad_medida_id' => $unidad?->id,
                    'stock_actual' => $stockActual,
                    'stock_minimo' => $stockMinimo,
                    'costo_unitario' => $costoUnitario,
                    'estado_herramienta' => $tipo === 'herramienta' ? 'disponible' : null,
                    'activo' => true,
                ]);

                $creados++;
            }

            $this->info("Hoja {$nombreHoja}: procesada.");
        }

        $this->newLine();
        $this->info("Ítems creados: {$creados}");
        $this->info("Ítems omitidos (ya existían): {$omitidos}");
        if ($ajustadosNegativos > 0) {
            $this->warn("Saldos negativos en el Excel ajustados a 0: {$ajustadosNegativos} (el sistema nunca permite stock negativo).");
        }
        $this->comment('Nota: la "ubicación" del Excel no sigue el formato PASILLO-ESTANTE-NIVEL del sistema, así que se importó vacía — asígnala manualmente desde el catálogo si la necesitas para pruebas.');

        return self::SUCCESS;
    }

    private function numero(mixed $valor): float
    {
        return is_numeric($valor) ? (float) $valor : 0.0;
    }

    private function numeroONulo(mixed $valor): ?float
    {
        return is_numeric($valor) ? (float) $valor : null;
    }
}
