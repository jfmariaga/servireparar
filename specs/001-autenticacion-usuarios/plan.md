# Implementation Plan: Autenticación y Gestión de Usuarios

**Branch**: `001-autenticacion-usuarios` | **Date**: 2026-08-24 · **Rev.**: 2026-09-01 (rol Vendedor) | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/001-autenticacion-usuarios/spec.md`

> **Revisión 2026-09-01**: se agrega **Vendedor** como 5º rol fijo (canal de venta sin OT, spec 003 US6).
> Cambios acotados: enum/lista de roles pasa de 4 a 5, `RolPrioridad` inserta `Vendedor` entre `Almacenista`
> y `Técnico`, `RolesSeeder` crea el rol y su permiso `manage-despachos`, y se añade una ruta placeholder
> `dashboard.vendedor`. Detalle en la sección [Revisión 2026-09-01](#revisión-2026-09-01--rol-vendedor).

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

---

## Revisión 2026-09-01 — Rol Vendedor

### Alcance

Quinto rol fijo del catálogo, requerido por el canal de venta mostrador sin OT (spec 003, US6). El Vendedor
recibe la solicitud del cliente, valida contra el inventario y crea Solicitudes de Despacho; no descuenta
stock, no gestiona catálogos ni auditorías. La lógica funcional del despacho vive en spec 003; aquí solo se
da de alta el rol, su prioridad de redirección y su permiso.

### Cambios de código

| Archivo | Cambio |
|---|---|
| `app/Enums/RolPrioridad.php` | Nuevo case `Vendedor = 'Vendedor'`; `ordenados()` → `[Administrador, JefeDeTaller, Almacenista, Vendedor, Tecnico]`; `rutaDashboard()` mapea `Vendedor` → `dashboard.vendedor`. |
| `database/seeders/RolesSeeder.php` | El `foreach (RolPrioridad::ordenados())` ya crea el rol nuevo automáticamente. Añadir permiso `manage-despachos` a la lista; asignarlo a `Administrador` y `Vendedor`; asignar también `manage-despachos` a `Almacenista` (recibe/remisiona/entrega) y darle `manage-inventario` de solo lectura ya lo tiene. Vendedor: `syncPermissions(['manage-despachos'])` (+ lectura de inventario vía policy). |
| `routes/web.php` | `Volt::route('/dashboard/vendedor', 'dashboard')->name('dashboard.vendedor')->middleware('role:Vendedor')` (placeholder, igual que los demás roles; spec 007 lo reemplaza). |
| Redirección post-login | Sin cambios de lógica: ya itera `RolPrioridad::ordenados()`; basta el nuevo case. |

### Data Model (incremental)

- `roles` (spatie): +1 fila `Vendedor`. Catálogo pasa a 5 roles fijos.
- `permissions` (spatie): +1 `manage-despachos`.
- Sin migraciones nuevas.

### Constitution Check (re-evaluación)

- ✅ **II. Roles y Autorización**: el rol nuevo se implementa 100% con spatie-permission y el enum de
  prioridad ya existente; sin lógica hardcodeada adicional.
- ✅ Resto de principios: sin impacto (no toca auth, ni trazabilidad, ni alcance más allá de lo que la
  cotización ya describe como consumo para externos sin OT).

### Verificación end-to-end (incremental)

1. `php artisan migrate:fresh --seed` → existen 5 roles; `Vendedor` tiene solo `manage-despachos`.
2. Crear un usuario con rol Vendedor desde `/usuarios`; login → redirige a `dashboard.vendedor`.
3. Usuario Vendedor: 403 en `/usuarios`, `/inventario/catalogos`, `/inventario/auditorias`; acceso
   permitido a `/despachos` (spec 003).
4. Usuario con roles Almacenista + Vendedor → login redirige al dashboard de Almacenista (mayor prioridad).
5. `php artisan test --filter=Auth`, `--filter=Usuarios`, `--filter=RoleMiddleware` en verde.
