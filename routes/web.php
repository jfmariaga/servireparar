<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\InventarioEtiquetaController;
use App\Http\Controllers\RemisionEntregaController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

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

    // Inventario / Bodega (spec 003)
    Volt::route('/inventario', 'inventario.dashboard')->name('inventario.dashboard');
    Volt::route('/inventario/catalogo', 'inventario.catalogo')->name('inventario.catalogo');
    Volt::route('/inventario/solicitudes', 'inventario.movimientos')->name('inventario.movimientos');
    Volt::route('/inventario/auditorias', 'inventario.auditoria')->name('inventario.auditoria');
    Volt::route('/inventario/categorias', 'inventario.catalogos')->name('inventario.catalogos');
    Route::get('/inventario/{inventario}/etiqueta', InventarioEtiquetaController::class)->name('inventario.etiqueta');

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
});
