<?php

namespace Database\Factories;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MovimientoInventarioFactory extends Factory
{
    protected $model = MovimientoInventario::class;

    public function definition(): array
    {
        return [
            'inventario_id' => Inventario::factory(),
            'tipo_mov' => 'entrada',
            'cantidad' => 10,
            'cantidad_disponible' => 10,
            'fecha' => now(),
            'motivo' => null,
            'referencia' => null,
            'usuario_id' => User::factory(),
            'proveedor_id' => null,
            'origen' => 'entrada_proveedor',
            'cliente_id' => null,
        ];
    }
}
