<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disparador de "plantilla esperada" (spec 006, FR-001)
    |--------------------------------------------------------------------------
    | Un correo entrante que no es respuesta de un hilo existente se registra
    | como caso nuevo solo si su asunto contiene este texto (sin distinguir
    | mayúsculas/minúsculas). Es una heurística simple y ajustable sin tocar
    | código — si la plantilla real del cliente exige otra regla, se cambia
    | aquí.
    */
    'asunto_disparador' => env('COTIZACIONES_ASUNTO_DISPARADOR', 'cotiz'),

    /*
    |--------------------------------------------------------------------------
    | Frecuencia del polling IMAP (minutos)
    |--------------------------------------------------------------------------
    */
    'polling_minutos' => (int) env('COTIZACIONES_POLLING_MINUTOS', 5),

];
