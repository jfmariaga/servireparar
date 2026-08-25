<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lanzada cuando se intenta descontar más stock del disponible para un
 * consumible (spec 003, FR-009). El sistema nunca permite stock negativo (SC-002).
 */
class StockInsuficienteException extends RuntimeException
{
    //
}
