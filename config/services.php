<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cuenta de correo oficial de Cotizaciones (spec 006, FR-010)
    |--------------------------------------------------------------------------
    | Credenciales IMAP para el polling de solicitudes de cotización entrantes
    | (ImapPollingProveedorCorreo). Sin configurar, el comando programado
    | simplemente no encuentra credenciales y no hace nada — no es requisito
    | para el resto del módulo, que se prueba con un fake en memoria.
    */
    'correo_cotizaciones' => [
        'host' => env('COTIZACIONES_IMAP_HOST'),
        'port' => (int) env('COTIZACIONES_IMAP_PORT', 993),
        'encryption' => env('COTIZACIONES_IMAP_ENCRYPTION', 'ssl'),
        'username' => env('COTIZACIONES_IMAP_USERNAME'),
        'password' => env('COTIZACIONES_IMAP_PASSWORD'),
    ],

];
