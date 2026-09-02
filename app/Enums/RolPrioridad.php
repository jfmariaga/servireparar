<?php

namespace App\Enums;

/**
 * Orden de prioridad de roles para redirección post-login cuando un usuario
 * tiene más de un rol asignado (spec 001, FR-010).
 */
enum RolPrioridad: string
{
    case Administrador = 'Administrador';
    case JefeDeTaller = 'Jefe de Taller';
    case Almacenista = 'Almacenista';
    case Vendedor = 'Vendedor';
    case Tecnico = 'Técnico';

    /**
     * @return array<string> Roles ordenados de mayor a menor prioridad.
     */
    public static function ordenados(): array
    {
        return [
            self::Administrador->value,
            self::JefeDeTaller->value,
            self::Almacenista->value,
            self::Vendedor->value,
            self::Tecnico->value,
        ];
    }

    /**
     * Ruta (name) del dashboard asociado a cada rol.
     */
    public static function rutaDashboard(string $rol): string
    {
        return match ($rol) {
            self::Administrador->value => 'dashboard.administrador',
            self::JefeDeTaller->value => 'dashboard.jefe-taller',
            self::Almacenista->value => 'dashboard.almacenista',
            self::Vendedor->value => 'dashboard.vendedor',
            self::Tecnico->value => 'dashboard.tecnico',
            default => 'dashboard',
        };
    }
}
