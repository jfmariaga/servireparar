<?php

namespace App\Support;

/**
 * Convierte los días trabajados (decimal, calculados como horas/jornada para
 * el costeo) a un texto legible para el taller: en horas si no completa una
 * jornada, en "día(s) y hora(s)" si la supera.
 */
class Jornada
{
    public static function humanoDesdeDias(float $dias): string
    {
        $horasJornada = (float) config('ot.horas_jornada_laboral', 8);
        $horasTotales = (int) round($dias * $horasJornada);

        if ($horasTotales < $horasJornada) {
            return $horasTotales.' hora'.($horasTotales === 1 ? '' : 's');
        }

        $diasEnteros = intdiv($horasTotales, (int) $horasJornada);
        $horasRestantes = $horasTotales % (int) $horasJornada;

        $texto = $diasEnteros.' día'.($diasEnteros === 1 ? '' : 's');

        if ($horasRestantes > 0) {
            $texto .= ' y '.$horasRestantes.' hora'.($horasRestantes === 1 ? '' : 's');
        }

        return $texto;
    }
}
