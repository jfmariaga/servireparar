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

// Spec 003 (Inventario/Bodega): avisa de despachos con mensajero sin firma física de vuelta.
Schedule::command('despacho:revisar-firmas-pendientes')->daily();

// Spec 006 (Compras/Cotizaciones), FR-010: polling IMAP de la cuenta de correo oficial.
Schedule::command('cotizaciones:procesar-correo')
    ->cron('*/'.(int) config('cotizaciones.polling_minutos', 5).' * * * *');
