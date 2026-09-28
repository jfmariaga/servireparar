<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Autorización por rol reutilizable en rutas de cualquier módulo, ej. ->middleware('role:Administrador')
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Hostinger termina el HTTPS en un proxy delante de PHP: sin esto,
        // Laravel ve cada petición como http:// aunque el navegador use
        // https://, y las URLs firmadas (ej. previsualización de subida de
        // Livewire) quedan generadas en https pero se validan contra http,
        // dando 401 siempre. "*" confía en el reenvío de cualquier proxy
        // upstream, razonable aquí porque PHP-FPM no es accesible directo.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

// Hostinger sirve `public_html` como raíz del dominio; el proyecto no tiene
// una carpeta `public/` propia (su contenido vive directamente en
// `public_html/`), así que hay que decirle a Laravel dónde quedó.
if (is_dir(__DIR__.'/../public_html')) {
    $app->usePublicPath(__DIR__.'/../public_html');
}

return $app;
