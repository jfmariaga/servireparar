<?php

namespace App\Providers;

use App\Contracts\ProveedorCorreoEntrante;
use App\Contracts\ProveedorCorreoSaliente;
use App\Events\MantenimientoPreventivoProximoAVencer;
use App\Events\OtCreada;
use App\Events\OtEntregada;
use App\Events\OtProximaAVencer;
use App\Events\StockBajo;
use App\Listeners\NotificarEventosEquipos;
use App\Listeners\NotificarEventosOt;
use App\Services\Correo\ImapPollingProveedorCorreo;
use App\Services\Correo\MailProveedorCorreoSaliente;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Correo de Cotizaciones (spec 006, FR-010): la lógica de negocio depende
        // de las interfaces, no de IMAP/SMTP directamente — los tests rebindean
        // ProveedorCorreoEntrante a un fake en memoria.
        $this->app->bind(ProveedorCorreoEntrante::class, ImapPollingProveedorCorreo::class);
        $this->app->bind(ProveedorCorreoSaliente::class, MailProveedorCorreoSaliente::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Avisos in-app del flujo de OT (Phase 11 / D5, micro-slice del spec 008).
        Event::listen(OtCreada::class, [NotificarEventosOt::class, 'otCreada']);
        Event::listen(OtEntregada::class, [NotificarEventosOt::class, 'otEntregada']);
        Event::listen(OtProximaAVencer::class, [NotificarEventosOt::class, 'otProximaAVencer']);
        Event::listen(StockBajo::class, [NotificarEventosOt::class, 'stockBajo']);

        // Avisos in-app de Equipos (spec 005), micro-slice del spec 008.
        Event::listen(MantenimientoPreventivoProximoAVencer::class, [NotificarEventosEquipos::class, 'mantenimientoProximoAVencer']);
    }
}
