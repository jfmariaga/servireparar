<?php

namespace Database\Seeders;

use App\Enums\RolPrioridad;
use App\Models\Especialidad;
use App\Models\Tecnico;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuarios de demostración para probar el flujo completo de OT (spec 002) y los
 * demás módulos: un usuario por rol + tres técnicos con ficha y sueldo.
 * Credenciales de desarrollo (password: `password`) — no usar en producción.
 * Idempotente: se puede re-correr sin duplicar.
 */
class UsuariosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->crearUsuario('Jefe de Taller Demo', 'jefe@servireparar.com', RolPrioridad::JefeDeTaller);
        $this->crearUsuario('Almacenista Demo', 'almacen@servireparar.com', RolPrioridad::Almacenista);
        $this->crearUsuario('Vendedor Demo', 'vendedor@servireparar.com', RolPrioridad::Vendedor);

        $tecnicos = [
            ['Carlos Soldador', 'tecnico1@servireparar.com', 'Estructuras y soldadura', 2_600_000],
            ['Diana Mecánica', 'tecnico2@servireparar.com', 'Mecánico', 2_400_000],
            ['Andrés Eléctrico', 'tecnico3@servireparar.com', 'Eléctrico', 2_800_000],
        ];

        foreach ($tecnicos as [$nombre, $email, $especialidad, $sueldo]) {
            $user = $this->crearUsuario($nombre, $email, RolPrioridad::Tecnico);
            $espId = Especialidad::firstOrCreate(['nombre' => $especialidad])->id;

            $tecnico = Tecnico::firstOrCreate(
                ['usuario_id' => $user->id],
                [
                    'especialidad_id' => $espId,
                    'fecha_ingreso' => now()->subMonths(8)->toDateString(),
                    'cargo' => 'Técnico de taller',
                    'tipo_contrato' => 'indefinido',
                    'activo' => true,
                ],
            );

            $tecnico->registrarSueldo($sueldo, now()->subMonths(8));
        }
    }

    private function crearUsuario(string $nombre, string $email, RolPrioridad $rol): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $nombre, 'password' => 'password', 'estado' => 'activo'],
        );

        if (! $user->hasRole($rol->value)) {
            $user->assignRole($rol->value);
        }

        return $user;
    }
}
