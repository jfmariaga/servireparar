<?php

namespace App\Console\Commands;

use App\Services\OrdenTrabajo\VencimientoOtService;
use Illuminate\Console\Command;

class RevisarVencimientosOt extends Command
{
    protected $signature = 'ot:revisar-vencimientos';

    protected $description = 'Dispara alertas de OT próximas a vencer o vencidas (spec 002, FR-010)';

    public function handle(VencimientoOtService $service): int
    {
        $disparadas = $service->revisar();

        $this->info("Alertas de vencimiento de OT disparadas: {$disparadas}");

        return self::SUCCESS;
    }
}
