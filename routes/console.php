<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Spec 005 (Equipos/Mantenimiento), FR-006: revisa mantenimientos preventivos próximos a vencer.
Schedule::command('mantenimientos:revisar-preventivos')->daily();

// Spec 002 (Órdenes de Trabajo), FR-010: revisa OT próximas a vencer o vencidas.
Schedule::command('ot:revisar-vencimientos')->daily();
