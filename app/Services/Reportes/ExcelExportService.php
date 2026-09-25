<?php

namespace App\Services\Reportes;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportación a Excel de listados (spec 007, US3, FR-006): usa PhpSpreadsheet
 * directamente (ya es dependencia del proyecto para importar el catálogo de
 * inventario, `ImportarInventarioExcel`) en vez de agregar maatwebsite/excel,
 * que no tiene una versión compatible con PHP 8.2 + phpoffice/phpspreadsheet
 * ^5.9 ya fijado en composer.json.
 */
class ExcelExportService
{
    /**
     * @param  array<int, string>  $encabezados
     * @param  iterable<int, array<int, mixed>>  $filas
     */
    public function descargar(string $nombreArchivo, array $encabezados, iterable $filas): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->fromArray($encabezados, null, 'A1');

        $numeroFila = 2;
        foreach ($filas as $fila) {
            $hoja->fromArray($fila, null, 'A'.$numeroFila);
            $numeroFila++;
        }

        foreach (range('A', $hoja->getHighestColumn()) as $columna) {
            $hoja->getColumnDimension($columna)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
