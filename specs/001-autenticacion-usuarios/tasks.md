# Tasks: Autenticación y Gestión de Usuarios

**Input**: Design documents from `specs/001-autenticacion-usuarios/` (plan.md, spec.md)

**Tests**: Incluidos — autenticación y autorización son código crítico de seguridad.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup (Shared Infrastructure)

- [x] T001 Configurar `config/auth.php` (guard `web`, provider `users`) y `config/sanctum.php`
  (stateful domains para el entorno de desarrollo/producción)
- [x] T002 [P] Configurar driver de correo en `.env` (`log` en dev, SMTP real en staging/producción —
  reutilizado luego por spec 006/008)

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Bloquea todos los demás módulos (todos requieren usuario autenticado)

- [x] T003 Migración `xxxx_add_estado_to_users_table.php` (añade `estado`, `telefono` a la tabla `users`
  base de Laravel)
- [x] T004 Modelo `app/Models/User.php`: `implements Authenticatable`, trait `HasRoles` (spatie), casts de
  `estado`
- [x] T005 Seeder `database/seeders/RolesSeeder.php`: crea los 4 roles (Administrador, Jefe de Taller,
  Almacenista, Técnico) y permisos base por rol
- [x] T006 Seeder `database/seeders/AdminUserSeeder.php`: crea el primer Administrador (bootstrap del
  sistema)
- [x] T007 Definir constante/enum de prioridad de roles para redirección multi-rol (FR-010) — ej.
  `app/Enums/RolPrioridad.php`
- [x] T008 Configurar `RouteServiceProvider`/middleware `throttle` estándar de Laravel para el login
  (Clarifications: sin bloqueo adicional)

**Checkpoint**: Roles, usuario admin inicial y throttle listos — las historias pueden implementarse

---

## Phase 3: User Story 1 - Iniciar sesión en el sistema (Priority: P1) 🎯 MVP

**Goal**: Login con correo/contraseña, redirección por prioridad de rol, rechazo si inactivo

**Independent Test**: Con el Administrador seed, iniciar sesión y verificar redirección correcta; probar
credenciales inválidas y usuario inactivo

### Tests for User Story 1

- [x] T009 [P] [US1] Feature test `tests/Feature/Auth/LoginTest.php`: éxito, credenciales inválidas
  (mensaje genérico), usuario inactivo rechazado
- [x] T010 [P] [US1] Feature test: redirección post-login según prioridad de rol (multi-rol)

### Implementation for User Story 1

- [x] T011 [US1] Componente Volt `app/Livewire/Auth/Login.php` (Ilustración 1)
- [x] T012 [US1] Lógica de redirección post-login usando `RolPrioridad` (T007)
- [x] T013 [US1] Ruta `/login`, `/logout` en `routes/web.php`
- [x] T014 [US1] Middleware/gate que rechaza login si `estado = inactivo`

**Checkpoint**: Login funcional de forma independiente — ya se puede entrar al sistema

---

## Phase 4: User Story 2 - Recuperar contraseña olvidada (Priority: P2)

**Goal**: Flujo nativo de Laravel `Password::sendResetLink`/`Password::reset`

**Independent Test**: Solicitar enlace, usarlo dentro del TTL, verificar invalidación de sesiones previas

### Tests for User Story 2

- [X] T015 [P] [US2] Feature test `tests/Feature/Auth/PasswordResetTest.php`: envío de enlace, reseteo
  exitoso, enlace expirado/usado rechazado

### Implementation for User Story 2

- [X] T016 [US2] Componente Volt `auth.forgot-password` (Ilustración 3) — implementado como Volt de archivo
  único en `resources/views/livewire/auth/forgot-password.blade.php`, mismo patrón que `auth.login`
- [X] T017 [US2] Componente Volt `auth.reset-password` — `resources/views/livewire/auth/reset-password.blade.php`
- [X] T018 [US2] Rutas `/forgot-password`, `/reset-password/{token}`
- [X] T019 [US2] Configurar TTL de expiración en `config/auth.php` (`passwords.users.expire`, ahora
  configurable vía `AUTH_PASSWORD_RESET_EXPIRE`)

**Checkpoint**: Login + recuperación de contraseña funcionan de forma independiente

---

## Phase 5: User Story 3 - Gestionar el propio perfil (Priority: P2)

**Goal**: Ver datos propios y cambiar contraseña desde el perfil

**Independent Test**: Usuario autenticado edita su contraseña, verifica el cambio en el siguiente login

### Tests for User Story 3

- [X] T020 [P] [US3] Feature test `tests/Feature/Auth/ProfileTest.php`: ver perfil, cambio de contraseña
  con contraseña actual requerida

### Implementation for User Story 3

- [X] T021 [US3] Componente Volt `auth.profile` (Ilustración 4) — `resources/views/livewire/auth/profile.blade.php`;
  edita nombre/teléfono/contraseña, roles solo de lectura (no auto-modificables)
- [X] T022 [US3] Ruta `/perfil`

**Checkpoint**: US1+US2+US3 funcionan de forma independiente

---

## Phase 6: User Story 4 - Administrar usuarios y roles (Priority: P1)

**Goal**: CRUD de usuarios + asignación de roles por el Administrador; protección del último Administrador

**Independent Test**: Crear usuario con rol Técnico, verificar que solo ve lo permitido a su rol

### Tests for User Story 4

- [x] T023 [P] [US4] Feature test `tests/Feature/Admin/UsuariosTest.php`: creación, edición, cambio de rol,
  activar/inactivar
- [x] T024 [P] [US4] Feature test: impide inactivar/eliminar al último Administrador activo (FR-008)
- [x] T025 [P] [US4] Policy test: usuario con rol Técnico recibe 403 en rutas de Administrador

### Implementation for User Story 4

- [x] T026 [US4] `app/Policies/UserPolicy.php` (manage-users, `canDeactivate()` valida último Admin)
- [x] T027 [US4] Componente Volt `app/Livewire/Admin/Usuarios/Index.php` (listado + filtro rol/estado)
- [x] T028 [US4] Componente Volt `app/Livewire/Admin/Usuarios/Form.php` (crear/editar + asignar roles)
- [x] T029 [US4] Ruta `/usuarios` protegida por `UserPolicy`

**Checkpoint**: Las 4 historias de usuario son funcionales de forma independiente

---

## Phase 7: Polish & Cross-Cutting Concerns

- [X] T030 [P] Middleware global de autorización por rol reutilizable para los demás módulos del sistema —
  se reutiliza el `RoleMiddleware` nativo de spatie/laravel-permission (alias `role`, `permission`,
  `role_or_permission` registrados en `bootstrap/app.php`) en vez de escribir uno propio, consistente con
  la Constitución (Principio I); aplicado como ejemplo a `/dashboard/{rol}` — ver
  `tests/Feature/Auth/RoleMiddlewareTest.php`
- [X] T031 Documentar en `AuthServiceProvider` el registro de todas las Policies del sistema (patrón a
  seguir por los demás specs) — `app/Providers/AuthServiceProvider.php`, registrado en
  `bootstrap/providers.php`
- [x] T032 Ejecutar `php artisan test --filter=Auth` y `--filter=Usuarios` en verde

---

## Phase 8: Revisión 2026-09-01 - Rol Vendedor (5º rol) — habilita spec 003 US6

> **Nota de alcance**: quinto rol fijo del catálogo, requerido por el canal de venta mostrador sin OT
> (spec 003, User Story 6). Sólo se da de alta el rol, su prioridad de redirección y su permiso; la lógica
> del despacho vive en spec 003. Sin migraciones nuevas (spatie ya tiene las tablas). Esta fase debe estar
> completa antes de la Fase 10 de `specs/003-inventario-bodega/tasks.md`.

- [x] T033 `app/Enums/RolPrioridad.php`: nuevo case `Vendedor = 'Vendedor'`; `ordenados()` pasa a
  `[Administrador, JefeDeTaller, Almacenista, Vendedor, Tecnico]`; `rutaDashboard()` mapea `Vendedor` →
  `dashboard.vendedor`
- [x] T034 `database/seeders/RolesSeeder.php`: añadir permiso `manage-despachos` a la lista base;
  `Vendedor->syncPermissions(['manage-despachos'])`; añadir `manage-despachos` a las de `Almacenista`
  (recibe/remisiona/entrega) y `Administrador`. El `foreach (RolPrioridad::ordenados())` ya crea el rol
  `Vendedor` automáticamente
- [x] T035 `routes/web.php`: ruta placeholder
  `Volt::route('/dashboard/vendedor', 'dashboard')->name('dashboard.vendedor')->middleware('role:Vendedor')`
  (igual que los demás roles; spec 007 la reemplaza con indicadores reales)
- [x] T036 [P] Feature test `tests/Feature/Auth/RoleMiddlewareTest.php` (ampliación): usuario con rol
  `Vendedor` accede a `dashboard.vendedor` y recibe 403 en `/usuarios`, `/inventario/categorias`,
  `/inventario/auditorias`; usuario con roles `Almacenista` + `Vendedor` es redirigido al dashboard de
  Almacenista (mayor prioridad)
- [x] T037 Revisar tests/seeders que asuman exactamente 4 roles (`AdminUserSeeder`, factories) y
  ejecutar `php artisan test --filter=Auth`, `--filter=Usuarios`, `--filter=RoleMiddleware` en verde
- [x] T038 Actualizar `README.md` (fila de `/speckit-tasks` del spec 001 → completado rev. 2026-09-01) y la
  tabla de estado de clarificación/plan si procede

**Checkpoint**: existe el rol `Vendedor` con permiso `manage-despachos` y su redirección post-login; spec
003 Fase 10 (US6) queda desbloqueada.

---

## Dependencies & Execution Order

- **Setup + Foundational** bloquean todo — sin roles y usuario admin seed, nada más funciona.
- **US1 (Login)** es el verdadero MVP: sin login no se puede demostrar nada del resto del sistema.
- **US2 (recuperación)** y **US3 (perfil)** son independientes entre sí y de US4, pueden paralelizarse tras
  US1.
- **US4 (Admin usuarios)** depende de US1 (necesita poder loguearse como Administrador) pero no de US2/US3.
- **Fase 8 (rol Vendedor)** sólo depende de la Fase 2 (`RolPrioridad`, `RolesSeeder`); es prerrequisito de
  la Fase 10 de spec 003 (venta mostrador sin OT).

## Implementation Strategy

MVP = US1 (Login) + US4 (Admin de usuarios), ya que sin poder crear usuarios reales no se puede dar de alta
al resto del personal que usará los demás módulos (spec 000, 002, 003, 004 dependen de tener usuarios con
rol asignado).
