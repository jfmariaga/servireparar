<?php

use App\Http\Controllers\Auth\LogoutController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/login', 'auth.login')->middleware('guest')->name('login');
Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Volt::route('/', 'dashboard')->name('dashboard');

    // Placeholders de dashboard por rol (spec 007 los reemplaza con indicadores reales)
    Volt::route('/dashboard/administrador', 'dashboard')->name('dashboard.administrador');
    Volt::route('/dashboard/jefe-taller', 'dashboard')->name('dashboard.jefe-taller');
    Volt::route('/dashboard/almacenista', 'dashboard')->name('dashboard.almacenista');
    Volt::route('/dashboard/tecnico', 'dashboard')->name('dashboard.tecnico');

    // Catálogos maestros (spec 000)
    Volt::route('/clientes', 'clientes.index')->name('clientes.index');
    Volt::route('/proveedores', 'proveedores.index')->name('proveedores.index');
    Volt::route('/contratistas', 'contratistas.index')->name('contratistas.index');

    // Administración de usuarios (spec 001)
    Volt::route('/usuarios', 'admin.usuarios.index')->name('usuarios.index');
});
