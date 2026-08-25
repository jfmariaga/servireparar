<?php

namespace Database\Factories;

use App\Models\AjusteAuditoria;
use App\Models\AuditoriaInventario;
use App\Models\Inventario;
use Illuminate\Database\Eloquent\Factories\Factory;

class AjusteAuditoriaFactory extends Factory
{
    protected $model = AjusteAuditoria::class;

    public function definition(): array
    {
        return [
            'auditoria_id' => AuditoriaInventario::factory(),
            'inventario_id' => Inventario::factory(),
            'stock_sistema' => 100,
            'stock_fisico' => 95,
            'motivo' => 'Diferencia detectada en conteo físico.',
            'estado' => 'pendiente',
            'aprobado_por' => null,
            'resuelto_en' => null,
        ];
    }
}
