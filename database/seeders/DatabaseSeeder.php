<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            AdminUserSeeder::class,
            CatalogosMaestrosSeeder::class,
            EspecialidadesSeeder::class,
            CategoriasInventarioSeeder::class,
            UnidadesMedidaSeeder::class,
            PrioridadesSeeder::class,
            EstadosOtSeeder::class,
            UsuariosDemoSeeder::class,
            InventarioDemoSeeder::class,
            FlujoOtDemoSeeder::class,
        ]);
    }
}
