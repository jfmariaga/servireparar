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

    /*
    |--------------------------------------------------------------------------
    | Sedes y numeración de cotizaciones
    |--------------------------------------------------------------------------
    | Cada cotización se numera con la sigla de su ciudad y un consecutivo
    | propio de esa sede: "BAQ-0446". Sedes independientes de las del canal de
    | despacho (config/despachos.php).
    */
    'sedes' => [
        'CTG' => 'Cartagena',
        'BAQ' => 'Barranquilla',
        'SMR' => 'Santa Marta',
        'MTR' => 'Montería',
        'RCH' => 'Riohacha',
    ],

    'sede_por_defecto' => env('COTIZACIONES_SEDE', 'BAQ'),

    /*
    |--------------------------------------------------------------------------
    | IVA aplicado a la cotización (porcentaje)
    |--------------------------------------------------------------------------
    | Los costos de servicios e insumos se manejan SIN IVA; el IVA se calcula
    | a nivel de documento, después del descuento de cabecera.
    */
    'iva_porcentaje' => (float) env('COTIZACIONES_IVA_PORCENTAJE', 19),

];
