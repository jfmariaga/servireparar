# Implementation Plan: Autenticación y Gestión de Usuarios

**Branch**: `001-autenticacion-usuarios` | **Date**: 2026-08-24 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/001-autenticacion-usuarios/spec.md`

## Summary

Autenticación basada en sesión Laravel (Sanctum SPA-mode, no tokens API), con login/logout, recuperación de
contraseña vía el flujo nativo `Password::sendResetLink`/`Password::reset` de Laravel, gestión de perfil
propio, y CRUD de usuarios + asignación de roles por el Administrador usando spatie/laravel-permission. Es
el segundo módulo a construir (junto a spec 000), prerrequisito de todos los demás por requerir login.

## Technical Context

**Language/Version**: PHP 8.2, Laravel 11 (^11.31)

**Primary Dependencies**: Laravel Sanctum 4.0 (autenticación de sesión), spatie/laravel-permission 6.9
(roles Administrador/Jefe de Taller/Almacenista/Técnico), Livewire 3.6.4 + Volt 1.7 (formularios de login/
registro/perfil), Laravel `Illuminate\Auth\Notifications\ResetPassword` (correo de recuperación)

**Storage**: MySQL/MariaDB — tablas `users` (base de Laravel, renombrada/extendida según convención del
proyecto), `password_reset_tokens` (nativa), `roles`/`permissions`/`model_has_roles` (spatie)

**Testing**: Pest/PHPUnit — feature tests de login (éxito, credenciales inválidas, usuario inactivo),
recuperación de contraseña, edición de perfil, CRUD de usuarios, y policy tests de multi-rol/redirección

**Target Platform**: Servidor Linux (Hostinger VPS), aplicación Laravel monolítica

**Project Type**: Web application — monolito Laravel + Livewire/Volt

**Performance Goals**: Login percibido < 5s (SC-001 del spec); no hay requisito de alta concurrencia dado
el tamaño del equipo interno de SERVIREPARAR

**Constraints**: Throttle estándar de Laravel para intentos de login fallidos (`RouteServiceProvider`
default: 5 intentos/minuto por IP+email, sin bloqueo adicional — ver Clarifications del spec)

**Scale/Scope**: Decenas de usuarios internos (no cientos); 4 roles fijos, sin editor de roles dinámico en
UI (los permisos por rol se definen en un seeder, no se exponen para edición)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- ✅ **I. Stack Tecnológico Fijo**: usa Sanctum + spatie/laravel-permission exactamente como exige el
  principio I, sin JWT ni paquetes de auth alternativos.
- ✅ **II. Roles y Autorización**: los 4 roles y su redirección por prioridad se implementan 100% con
  spatie-permission (`hasRole()`/`getRoleNames()`), sin lógica de roles hardcodeada fuera de eso.
- ✅ **III. Alcance Cerrado**: sin auto-registro público (FR-011 del spec) — se respeta el alcance cerrado,
  no se agrega funcionalidad no solicitada pese a que el mockup la insinuara.
- ✅ **IV. Trazabilidad**: `fecha_registro`, `estado` y `updated_at` cubren la trazabilidad básica de
  usuarios; cambios de rol quedan en la tabla `model_has_roles` de spatie (con timestamps si se activa).
- ✅ **V. Entrega Modular por Fases**: demostrable de forma aislada (login + gestión de usuarios) sin
  depender de otros módulos funcionales.

Sin violaciones. No se requiere sección de Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/001-autenticacion-usuarios/
├── plan.md              # This file
└── spec.md              # Feature specification (ya existente)
```

### Source Code (repository root)

```text
app/
├── Models/
│   └── User.php                          # extiende Authenticatable + HasRoles (spatie)
├── Livewire/
│   └── Auth/
│       ├── Login.php                     # Volt component
│       ├── ForgotPassword.php
│       ├── ResetPassword.php
│       └── Profile.php
├── Livewire/
│   └── Admin/
│       └── Usuarios/
│           ├── Index.php                 # listado + filtro por rol/estado
│           └── Form.php                  # crear/editar usuario + asignar roles
└── Policies/
    └── UserPolicy.php                    # manage-users, impide inactivar último Administrador

database/
├── migrations/
│   ├── xxxx_add_estado_to_users_table.php
│   └── xxxx_create_permission_tables.php  # publicada por spatie/laravel-permission
└── seeders/
    ├── RolesSeeder.php                    # crea los 4 roles + permisos base
    └── AdminUserSeeder.php                # primer Administrador (bootstrap)

routes/
└── web.php                # /login, /logout, /forgot-password, /reset-password, /perfil, /usuarios

tests/
└── Feature/
    ├── Auth/
    │   ├── LoginTest.php
    │   ├── PasswordResetTest.php
    │   └── ProfileTest.php
    └── Admin/
        └── UsuariosTest.php
```

**Structure Decision**: Monolito Laravel estándar, reutilizando el scaffolding de auth de Laravel/Livewire
(no se usa Breeze/Jetstream como paquete — se implementa a medida sobre Volt para mantener consistencia de
UI con el resto del sistema, según los mockups Ilustraciones 1-4).

## Data Model

### User (tabla `users`, extendida)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nombre | string(150) | |
| correo (`email`) | string(150) unique | Identificador de login |
| password | string | Hash bcrypt/argon2 (default Laravel) |
| telefono | string(20) nullable | |
| estado | enum('activo','inactivo') default 'activo' | |
| fecha_registro (`created_at`) | timestamp | Nativo de Laravel |
| timestamps | | |

### Roles/Permisos (tablas de spatie/laravel-permission, sin modificar el paquete)

- `roles`: Administrador, Jefe de Taller, Almacenista, Técnico (seed fijo, orden de prioridad definido en
  código, no en base de datos, para el cálculo de FR-010).
- `model_has_roles`: relación N:N usuario↔rol.
- Permisos base por rol se definen en `RolesSeeder` y se verifican en cada Policy del sistema (no solo en
  este módulo).

## Verificación end-to-end

1. `php artisan migrate --seed` crea los 4 roles y un Administrador inicial.
2. Login con el Administrador seed → redirige a su tablero (aún no existe, pero la ruta post-login debe
   resolver correctamente por prioridad de rol).
3. Crear un usuario nuevo con rol Técnico desde `/usuarios`, cerrar sesión, iniciar sesión como ese
   usuario, verificar que accede solo a lo permitido por su rol (403 en rutas de Administrador).
4. Probar recuperación de contraseña con Mailtrap/log driver en entorno de desarrollo — el enlace debe
   expirar según el TTL configurado (`config/auth.php`, `passwords.users.expire`).
5. Intentar inactivar al único Administrador activo → debe rechazarse (FR-008).
6. `php artisan test --filter=Auth` y `--filter=Usuarios` en verde.
