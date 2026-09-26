<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

/**
 * Valores por defecto de los umbrales editables por el Administrador (spec 008,
 * T004) — antes hardcodeados en `config/ot.php`, `config/despachos.php` y en
 * `MantenimientoPreventivoService::revisarVencimientos()`.
 */
class ConfiguracionesSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'clave' => 'ot.dias_umbral_vencimiento',
                'valor' => (string) config('ot.dias_umbral_vencimiento', 2),
                'descripcion' => 'Días de anticipación para marcar una OT como próxima a vencer (spec 002, FR-010).',
            ],
            [
                'clave' => 'equipos.dias_antelacion_mantenimiento',
                'valor' => '7',
                'descripcion' => 'Días de anticipación para avisar de un mantenimiento preventivo próximo a vencer (spec 005, FR-006).',
            ],
            [
                'clave' => 'despachos.dias_alerta_firma_pendiente',
                'valor' => (string) config('despachos.dias_alerta_firma_pendiente', 3),
                'descripcion' => 'Días sin volver la firma física de un despacho con mensajero para alertar a Bodega/Vendedor.',
            ],
        ];

        foreach ($defaults as $config) {
            Configuracion::firstOrCreate(['clave' => $config['clave']], $config);
        }
    }
}
