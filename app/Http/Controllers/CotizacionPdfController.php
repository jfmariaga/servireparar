<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Services\Cotizaciones\GenerarPdfCotizacionService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Vista previa / impresión de la Cotización en PDF, sin que esto implique
 * enviarla al cliente por correo (mismo patrón que RemisionEntregaController).
 */
class CotizacionPdfController extends Controller
{
    public function __invoke(Cotizacion $cotizacion): SymfonyResponse
    {
        Gate::authorize('view', $cotizacion);

        $pdf = app(GenerarPdfCotizacionService::class)->generar($cotizacion);

        return $pdf->stream('cotizacion-'.$cotizacion->numero.'.pdf');
    }
}
