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

- [ ] T015 [P] [US2] Feature test `tests/Feature/Auth/PasswordResetTest.php`: envío de enlace, reseteo
  exitoso, enlace expirado/usado rechazado

### Implementation for User Story 2

- [ ] T016 [US2] Componente Volt `app/Livewire/Auth/ForgotPassword.php` (Ilustración 3)
- [ ] T017 [US2] Componente Volt `app/Livewire/Auth/ResetPassword.php`
- [ ] T018 [US2] Rutas `/forgot-password`, `/reset-password/{token}`
- [ ] T019 [US2] Configurar TTL de expiración en `config/auth.php` (`passwords.users.expire`)

**Checkpoint**: Login + recuperación de contraseña funcionan de forma independiente

---

## Phase 5: User Story 3 - Gestionar el propio perfil (Priority: P2)

**Goal**: Ver datos propios y cambiar contraseña desde el perfil

**Independent Test**: Usuario autenticado edita su contraseña, verifica el cambio en el siguiente login

### Tests for User Story 3

- [ ] T020 [P] [US3] Feature test `tests/Feature/Auth/ProfileTest.php`: ver perfil, cambio de contraseña
  con contraseña actual requerida

### Implementation for User Story 3

- [ ] T021 [US3] Componente Volt `app/Livewire/Auth/Profile.php` (Ilustración 4)
- [ ] T022 [US3] Ruta `/perfil`

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

- [ ] T030 [P] Middleware global de autorización por rol reutilizable (`EnsureRole` o similar) para los
  demás módulos del sistema
- [ ] T031 Documentar en `AuthServiceProvider` el registro de todas las Policies del sistema (patrón a
  seguir por los demás specs)
- [x] T032 Ejecutar `php artisan test --filter=Auth` y `--filter=Usuarios` en verde

---

## Dependencies & Execution Order

- **Setup + Foundational** bloquean todo — sin roles y usuario admin seed, nada más funciona.
- **US1 (Login)** es el verdadero MVP: sin login no se puede demostrar nada del resto del sistema.
- **US2 (recuperación)** y **US3 (perfil)** son independientes entre sí y de US4, pueden paralelizarse tras
  US1.
- **US4 (Admin usuarios)** depende de US1 (necesita poder loguearse como Administrador) pero no de US2/US3.

## Implementation Strategy

MVP = US1 (Login) + US4 (Admin de usuarios), ya que sin poder crear usuarios reales no se puede dar de alta
al resto del personal que usará los demás módulos (spec 000, 002, 003, 004 dependen de tener usuarios con
rol asignado).
