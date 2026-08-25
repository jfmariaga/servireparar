<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Services\Inventario\BarcodeService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class InventarioEtiquetaController extends Controller
{
    public function __invoke(Inventario $inventario, BarcodeService $barcodeService): Response
    {
        Gate::authorize('viewAny', Inventario::class);

        $png = $barcodeService->generarPng($inventario->codigo_barras ?? $inventario->codigo);

        return response($png, 200, ['Content-Type' => 'image/png']);
    }
}
