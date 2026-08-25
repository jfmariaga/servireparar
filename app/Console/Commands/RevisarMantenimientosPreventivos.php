<?php

namespace App\Console\Commands;

use App\Services\Equipos\MantenimientoPreventivoService;
use Illuminate\Console\Command;

class RevisarMantenimientosPreventivos extends Command
{
    protected $signature = 'mantenimientos:revisar-preventivos';

    protected $description = 'Dispara alertas de mantenimiento preventivo próximo a vencer (spec 005, FR-006)';

    public function handle(MantenimientoPreventivoService $service): int
    {
        $disparadas = $service->revisarVencimientos();

        $this->info("Alertas de mantenimiento preventivo disparadas: {$disparadas}");

        return self::SUCCESS;
    }
}
