<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\InventarioEtiquetaController;
use App\Http\Controllers\RemisionEntregaController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// TEMPORAL — diagnóstico del 401 en URLs firmadas (previsualización de Livewire) en
// Hostinger. Quitar en cuanto se confirme la causa.
// TEMPORAL — dump exacto de lo que Laravel recibe en una petición REAL a una
// ruta firmada, para comparar contra lo que se firmó. Quitar junto con la de
// arriba.
Route::get('/debug-firma/{filename}', function (\Illuminate\Http\Request $request, $filename) {
    return response()->json([
        'filename_recibido' => $filename,
        'query_completo_recibido' => $request->query(),
        'full_url_recibida' => $request->fullUrl(),
        'url_sin_query' => $request->url(),
        'hasValidSignature' => $request->hasValidSignature(),
        'path' => $request->path(),
    ]);
})->name('debug.firma');

Route::get('/debug-generar-firma', function () {
    return response()->json([
        'url' => \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'debug.firma', now()->addMinutes(30), ['filename' => 'diagnostico-prueba.png']
        ),
    ]);
});

Route::get('/debug-scheme', function () {
    // Genera una URL firmada real (mismo mecanismo que usa Livewire para
    // previsualizar) y la valida en la MISMA petición, para descartar que
    // el problema sea de tiempo/red entre generación y uso.
    $urlFirmada = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'livewire.preview-file', now()->addMinutes(30)->endOfHour(), ['filename' => 'diagnostico-prueba.png']
    );
    $subRequest = \Illuminate\Http\Request::create($urlFirmada, 'GET');

    return response()->json([
        'url_firmada_generada' => $urlFirmada,
        'esa_misma_url_es_valida_recien_generada' => $subRequest->hasValidSignature(),
        'route_cache_activo' => app()->routesAreCached(),
        'config_cache_activo' => app()->configurationIsCached(),
        'request_getScheme' => request()->getScheme(),
        'request_isSecure' => request()->isSecure(),
        'request_fullUrl' => request()->fullUrl(),
        'server_HTTPS' => $_SERVER['HTTPS'] ?? null,
        'server_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null,
        'server_SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? null,
        'app_url_config' => config('app.url'),
        'trusted_proxies' => \Illuminate\Http\Request::getTrustedProxies(),
        'now_server' => now()->toDateTimeString(),
        'now_utc' => now('UTC')->toDateTimeString(),
    ]);
});

Volt::route('/login', 'auth.login')->middleware('guest')->name('login');
Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

Volt::route('/forgot-password', 'auth.forgot-password')->middleware('guest')->name('password.request');
Volt::route('/reset-password/{token}', 'auth.reset-password')->middleware('guest')->name('password.reset');

Route::middleware('auth')->group(function () {
    Volt::route('/', 'dashboard')->name('dashboard');
    Volt::route('/perfil', 'auth.profile')->name('perfil');

    // Placeholders de dashboard por rol (spec 007 los reemplaza con indicadores reales)
    Volt::route('/dashboard/administrador', 'dashboard')->name('dashboard.administrador')->middleware('role:Administrador');
    Volt::route('/dashboard/jefe-taller', 'dashboard')->name('dashboard.jefe-taller')->middleware('role:Jefe de Taller');
    Volt::route('/dashboard/almacenista', 'dashboard')->name('dashboard.almacenista')->middleware('role:Almacenista');
    Volt::route('/dashboard/vendedor', 'dashboard')->name('dashboard.vendedor')->middleware('role:Vendedor');
    Volt::route('/dashboard/tecnico', 'dashboard')->name('dashboard.tecnico')->middleware('role:Técnico');

    // Catálogos maestros (spec 000)
    Volt::route('/clientes', 'clientes.index')->name('clientes.index');
    Volt::route('/proveedores', 'proveedores.index')->name('proveedores.index');
    Volt::route('/contratistas', 'contratistas.index')->name('contratistas.index');

    // Equipos (spec 005)
    Volt::route('/equipos', 'equipos.index')->name('equipos.index');
    Volt::route('/equipos/{equipo}/historial', 'equipos.historial')->name('equipos.historial');

    // Inventario / Bodega (spec 003)
    Volt::route('/inventario', 'inventario.dashboard')->name('inventario.dashboard');
    Volt::route('/inventario/catalogo', 'inventario.catalogo')->name('inventario.catalogo');
    Volt::route('/inventario/solicitudes', 'inventario.movimientos')->name('inventario.movimientos');
    Volt::route('/inventario/insumos-ot', 'inventario.solicitudes-ot')->name('insumos-ot');
    Volt::route('/inventario/prestamos-herramienta', 'inventario.prestamos-herramienta')->name('prestamos-herramienta');
    Volt::route('/inventario/auditorias', 'inventario.auditoria')->name('inventario.auditoria');
    Volt::route('/inventario/categorias', 'inventario.catalogos')->name('inventario.catalogos');
    Route::get('/inventario/{inventario}/etiqueta', InventarioEtiquetaController::class)->name('inventario.etiqueta');

    // Órdenes de Trabajo (spec 002)
    Route::middleware('can:viewAny,App\Models\OrdenTrabajo')->group(function () {
        Volt::route('/ordenes-trabajo', 'ordenes-trabajo.tablero')->name('ordenes-trabajo.tablero');
        Volt::route('/ordenes-trabajo/crear', 'ordenes-trabajo.crear')->name('ordenes-trabajo.crear');
        Volt::route('/ordenes-trabajo/{ordenTrabajo}/costeo', 'ordenes-trabajo.costeo')->name('ordenes-trabajo.costeo');
        Volt::route('/ordenes-trabajo/{ordenTrabajo}', 'ordenes-trabajo.detalle')->name('ordenes-trabajo.detalle');
    });

    // Despachos / venta mostrador sin OT (spec 003, US6)
    Route::middleware('role:Vendedor|Almacenista|Administrador')->group(function () {
        Volt::route('/despachos', 'despacho.index')->name('despachos.index');
        Volt::route('/despachos/nueva', 'despacho.form')->name('despachos.nueva');
        Volt::route('/despachos/{solicitud}', 'despacho.entrega')->name('despachos.detalle');
        Route::get('/despachos/{solicitud}/remision', RemisionEntregaController::class)->name('despachos.remision');
    });

    // Administración de usuarios (spec 001)
    Volt::route('/usuarios', 'admin.usuarios.index')->name('usuarios.index');

    // Catálogo de especialidades (spec 004)
    Volt::route('/especialidades', 'personal.especialidades')->name('especialidades.index');

    // Desempeño del técnico (spec 004, US3)
    Volt::route('/personal/desempeno', 'personal.desempeno')->name('personal.desempeno');

    // Auditoría / actividad reciente (pulido de producto: bitácora de OT unificada)
    Volt::route('/reportes/auditoria', 'reportes.auditoria')->name('reportes.auditoria')->middleware('role:Administrador');

    // Notificaciones (spec 008, T011): listado completo más allá de la campana
    Volt::route('/notificaciones', 'notificaciones.index')->name('notificaciones.index');

    // Configuración de umbrales editables (spec 008, T003-T006)
    Volt::route('/configuraciones', 'configuraciones.index')->name('configuraciones.index')->middleware('permission:manage-configuraciones');

    // Cotizaciones a clientes (spec 006)
    Route::middleware('permission:manage-cotizaciones')->group(function () {
        Volt::route('/cotizaciones', 'cotizaciones.tablero')->name('cotizaciones.tablero');
        Volt::route('/cotizaciones/servicios', 'cotizaciones.servicios-maestra')->name('cotizaciones.servicios');
        Volt::route('/cotizaciones/{cotizacion}', 'cotizaciones.gestionar')->name('cotizaciones.gestionar');
    });

    // Compras a proveedores (spec 006, US4)
    Route::middleware('permission:manage-compras')->group(function () {
        Volt::route('/compras', 'compras.tablero')->name('compras.tablero');
        Volt::route('/compras/nueva', 'compras.form')->name('compras.nueva');
        Volt::route('/compras/{compra}', 'compras.gestionar')->name('compras.gestionar');
    });
});
