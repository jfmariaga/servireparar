<?php

namespace App\Providers;

use App\Events\OtCreada;
use App\Events\OtEntregada;
use App\Events\OtProximaAVencer;
use App\Events\StockBajo;
use App\Listeners\NotificarEventosOt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }
}
