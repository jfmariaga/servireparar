<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Spec 005 (Equipos/Mantenimiento), FR-006: revisa mantenimientos preventivos próximos a vencer.
Schedule::command('mantenimientos:revisar-preventivos')->daily();
