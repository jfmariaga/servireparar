<?php

namespace App\Console\Commands;

use App\Contracts\ProveedorCorreoEntrante;
use App\Services\Cotizaciones\ProcesarCorreoEntranteService;
use Illuminate\Console\Command;

/**
 * Polling de la cuenta de correo oficial de Cotizaciones (spec 006, FR-010).
 * Agendado en `routes/console.php`.
 */
class ProcesarCorreoCotizaciones extends Command
{
    protected $signature = 'cotizaciones:procesar-correo';

    protected $description = 'Consulta la cuenta de correo oficial y registra/actualiza casos de cotización';

    public function handle(ProveedorCorreoEntrante $proveedor, ProcesarCorreoEntranteService $servicio): int
    {
        $mensajes = $proveedor->fetchNuevosMensajes();
        $procesados = 0;

        foreach ($mensajes as $mensaje) {
            $servicio->procesar($mensaje);
            $proveedor->marcarProcesado($mensaje);
            $procesados++;
        }

        $this->info("Correos de cotización procesados: {$procesados}");

        return self::SUCCESS;
    }
}
