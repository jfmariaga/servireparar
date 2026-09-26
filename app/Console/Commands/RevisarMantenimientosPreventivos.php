<?php

namespace App\Console\Commands;

use App\Models\Configuracion;
use App\Services\Equipos\MantenimientoPreventivoService;
use Illuminate\Console\Command;

class RevisarMantenimientosPreventivos extends Command
{
    protected $signature = 'mantenimientos:revisar-preventivos';

    protected $description = 'Dispara alertas de mantenimiento preventivo próximo a vencer (spec 005, FR-006)';

    public function handle(MantenimientoPreventivoService $service): int
    {
        $diasAntelacion = (int) Configuracion::obtener('equipos.dias_antelacion_mantenimiento', 7);
        $disparadas = $service->revisarVencimientos($diasAntelacion);

        $this->info("Alertas de mantenimiento preventivo disparadas: {$disparadas}");

        return self::SUCCESS;
    }
}
