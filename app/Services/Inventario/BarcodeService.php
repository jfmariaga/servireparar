<?php

namespace App\Services\Inventario;

use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Wrapper sobre picqer/php-barcode-generator (spec 003, FR-013) — aislado para
 * poder cambiar de librería sin afectar el resto del módulo.
 */
class BarcodeService
{
    public function generarPng(string $codigo): string
    {
        $generator = new BarcodeGeneratorPNG();

        return $generator->getBarcode($codigo, BarcodeGeneratorPNG::TYPE_CODE_128);
    }
}
