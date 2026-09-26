<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sedes (ciudades) del canal de despacho
    |--------------------------------------------------------------------------
    | Las remisiones se identifican por ciudad usando la sigla aeroportuaria
    | (IATA) de cada una — ej. "REMISIÓN BAQ" para Barranquilla. Al crear una
    | solicitud de despacho se elige la sede; el PDF de la remisión la muestra
    | junto al título. Editar esta lista para agregar/quitar ciudades.
    */
    'sedes' => [
        'BAQ' => 'Barranquilla',
        'BOG' => 'Bogotá',
        'MDE' => 'Medellín',
        'CTG' => 'Cartagena',
        'CLO' => 'Cali',
        'SMR' => 'Santa Marta',
        'ADZ' => 'San Andrés',
    ],

    // Sede preseleccionada al crear una solicitud (una de las claves de 'sedes').
    'sede_por_defecto' => env('DESPACHO_SEDE', 'BAQ'),

    /*
    |--------------------------------------------------------------------------
    | Alerta de firma física pendiente
    |--------------------------------------------------------------------------
    | Días desde que una solicitud queda `despachada` (salió con mensajero)
    | sin que vuelva el papel firmado por el cliente, antes de avisar a
    | Bodega y Ventas (`despacho:revisar-firmas-pendientes`).
    */
    'dias_alerta_firma_pendiente' => env('DESPACHO_DIAS_ALERTA_FIRMA', 3),

];
