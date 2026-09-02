<?php

namespace App\Http\Controllers;

use App\Models\SolicitudDespacho;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Remisión de entrega imprimible (spec 003, US6, FR-023): PDF con el consecutivo
 * `REM-#####`, todas las líneas (inventario + compra externa) y la firma digital
 * del receptor embebida.
 */
class RemisionEntregaController extends Controller
{
    public function __invoke(SolicitudDespacho $solicitud): SymfonyResponse
    {
        Gate::authorize('view', $solicitud);

        abort_if($solicitud->remision === null, Response::HTTP_NOT_FOUND, 'La solicitud aún no tiene remisión.');

        $solicitud->load(['cliente', 'vendedor', 'detalles.inventario', 'remision.generadaPor']);

        $pdf = Pdf::loadView('pdf.remision-entrega', ['solicitud' => $solicitud]);

        return $pdf->stream('remision-'.$solicitud->remision->numero.'.pdf');
    }
}
