<?php

namespace App\Console\Commands;

use App\Services\Inventario\FirmaFisicaPendienteService;
use Illuminate\Console\Command;

class RevisarFirmasPendientesDespacho extends Command
{
    protected $signature = 'despacho:revisar-firmas-pendientes {--reenviar : Vuelve a avisar aunque ya se haya alertado}';

    protected $description = 'Avisa de solicitudes despachadas con mensajero sin firma física de vuelta hace varios días';

    public function handle(FirmaFisicaPendienteService $service): int
    {
        $disparadas = $service->revisar((bool) $this->option('reenviar'));

        $this->info("Alertas de firma física pendiente disparadas: {$disparadas}");

        return self::SUCCESS;
    }
}
