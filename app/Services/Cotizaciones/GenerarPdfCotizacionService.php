<?php

namespace App\Services\Cotizaciones;

use App\Models\Cotizacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

/**
 * PDF de la Cotización (spec 006, FR-004), mismo patrón que
 * `RemisionEntregaController`/`RemisionEntregada` (dompdf).
 */
class GenerarPdfCotizacionService
{
    public function generar(Cotizacion $cotizacion): PdfDocument
    {
        $cotizacion->loadMissing('cliente', 'detalles.servicio', 'detalles.inventario');

        return Pdf::loadView('pdf.cotizacion', ['cotizacion' => $cotizacion]);
    }
}
