<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Contratista;
use App\Models\Proveedor;
use Illuminate\Database\Seeder;

class CatalogosMaestrosSeeder extends Seeder
{
    /**
     * Datos de ejemplo para desarrollo/QA (spec 000).
     */
    public function run(): void
    {
        Cliente::firstOrCreate(
            ['nit' => '900123456-1'],
            [
                'nombre' => 'TALMA Servicios Aeroportuarios',
                'telefono' => '3001234567',
                'correo' => 'contacto@talma.com.co',
                'direccion' => 'Aeropuerto Rafael Núñez, Cartagena',
                'estado' => 'activo',
            ]
        );

        Cliente::firstOrCreate(
            ['nit' => '900654321-2'],
            [
                'nombre' => 'Avianca Cargo',
                'telefono' => '3007654321',
                'correo' => 'operaciones@avianca.com',
                'direccion' => 'Aeropuerto El Dorado, Bogotá',
                'estado' => 'activo',
            ]
        );

        Proveedor::firstOrCreate(
            ['nit' => '800111222-3'],
            [
                'nombre' => 'Almacén Industrial del Caribe',
                'correo' => 'ventas@almacenindustrial.com',
                'direccion' => 'Zona Industrial, Cartagena',
                'estado' => 'activo',
            ]
        );

        Contratista::firstOrCreate(
            ['correo' => 'contacto@solucionesballestas.com'],
            [
                'nombre' => 'Soluciones Ballestas',
                'especialidad' => 'Calcomanías y rotulación',
                'telefono' => '3009876543',
                'estado' => 'activo',
            ]
        );
    }
}
