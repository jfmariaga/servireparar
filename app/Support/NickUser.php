<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Sugerencia de NickUser (usuario de acceso) a partir del nombre completo:
 * primera letra del primer nombre + última palabra del nombre, sin acentos
 * ni espacios. Es solo una sugerencia editable — el admin decide el valor
 * final y resuelve choques manualmente (no hay separación de apellido en
 * el nombre almacenado, así que la extracción es una aproximación).
 */
class NickUser
{
    public static function sugerir(string $nombreCompleto): string
    {
        $partes = preg_split('/\s+/', trim($nombreCompleto)) ?: [];
        $partes = array_values(array_filter($partes, fn ($p) => $p !== ''));

        if ($partes === []) {
            return '';
        }

        $inicial = mb_substr($partes[0], 0, 1);
        $ultimo = $partes[count($partes) - 1];

        return Str::of($inicial.$ultimo)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]/', '')
            ->value();
    }
}
