# Tasks: Catálogos Maestros (Clientes, Proveedores, Contratistas)

**Input**: Design documents from `specs/000-catalogos-maestros/` (plan.md, spec.md)

**Tests**: Incluidos — el spec define criterios de aceptación verificables (duplicados, disponibilidad en
selectores, activo/inactivo) que ameritan feature tests.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup (Shared Infrastructure)

- [ ] T001 Verificar `composer.json` (Laravel 11, Livewire 3.6.4, Volt 1.7, Sanctum 4.0,
  spatie/laravel-permission 6.9) y correr `composer install`
- [ ] T002 Configurar conexión MySQL/MariaDB en `.env` (local + staging) y confirmar `php artisan migrate`
  corre sin errores sobre una base vacía
- [ ] T003 [P] Publicar migraciones de spatie/laravel-permission (`php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`)

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Sin esto, ninguna historia de usuario puede implementarse

- [ ] T004 Crear migración `xxxx_create_clientes_table.php` según Data Model del plan.md
- [ ] T005 [P] Crear migración `xxxx_create_proveedores_table.php`
- [ ] T006 [P] Crear migración `xxxx_create_contratistas_table.php`
- [ ] T007 Crear modelo `app/Models/Cliente.php` (fillable, casts `estado`)
- [ ] T008 [P] Crear modelo `app/Models/Proveedor.php`
- [ ] T009 [P] Crear modelo `app/Models/Contratista.php`
- [ ] T010 Crear `app/Policies/ClientePolicy.php`, `ProveedorPolicy.php`, `ContratistaPolicy.php`
  (permiso base: solo Administrador y Jefe de Taller gestionan; registrar en `AuthServiceProvider`)

**Checkpoint**: Migraciones + modelos + policies listos — las historias de usuario pueden implementarse

---

## Phase 3: User Story 1 - Registrar un cliente antes de poder crear una OT (Priority: P1) 🎯 MVP

**Goal**: CRUD de Cliente con advertencia de duplicado por NIT/correo

**Independent Test**: Registrar un cliente y verificar que aparece disponible en cualquier selector que lo
consuma (aislado con un selector de prueba si specs 002/006 aún no existen)

### Tests for User Story 1

- [ ] T011 [P] [US1] Feature test: creación de cliente válida en `tests/Feature/Catalogos/ClienteTest.php`
- [ ] T012 [P] [US1] Feature test: advertencia (no bloqueo) al registrar NIT/correo duplicado
- [ ] T013 [P] [US1] Feature test: cliente inactivo no aparece en selectores activos (FR-005)

### Implementation for User Story 1

- [ ] T014 [US1] Componente Volt `app/Livewire/Catalogos/Clientes/Index.php` (listado + filtro
  activo/inactivo)
- [ ] T015 [US1] Componente Volt `app/Livewire/Catalogos/Clientes/Form.php` (crear/editar, valida
  duplicado sin bloquear)
- [ ] T016 [US1] Ruta `Volt::route('/clientes', ...)` en `routes/web.php`, protegida por `ClientePolicy`
- [ ] T017 [US1] Vista Blade/Volt del formulario (nombre, NIT, teléfono, correo, dirección, estado)

**Checkpoint**: User Story 1 funcional de forma independiente

---

## Phase 4: User Story 2 - Registrar un proveedor antes de poder recibir inventario (Priority: P1)

**Goal**: CRUD de Proveedor, mismo patrón que Cliente

**Independent Test**: Registrar un proveedor y verificarlo disponible en un selector de prueba

### Tests for User Story 2

- [ ] T018 [P] [US2] Feature test en `tests/Feature/Catalogos/ProveedorTest.php` (creación, duplicado,
  activo/inactivo)

### Implementation for User Story 2

- [ ] T019 [P] [US2] Componente Volt `app/Livewire/Catalogos/Proveedores/Index.php`
- [ ] T020 [US2] Componente Volt `app/Livewire/Catalogos/Proveedores/Form.php`
- [ ] T021 [US2] Ruta `/proveedores` protegida por `ProveedorPolicy`

**Checkpoint**: User Stories 1 y 2 funcionan de forma independiente

---

## Phase 5: User Story 3 - Registrar un contratista antes de poder asignarlo a una OT (Priority: P1)

**Goal**: CRUD de Contratista, entidad nueva distinta de Proveedor/Técnico

**Independent Test**: Registrar un contratista y verificarlo disponible en un selector de prueba

### Tests for User Story 3

- [ ] T022 [P] [US3] Feature test en `tests/Feature/Catalogos/ContratistaTest.php` (creación, duplicado,
  activo/inactivo, campo especialidad texto libre)

### Implementation for User Story 3

- [ ] T023 [P] [US3] Componente Volt `app/Livewire/Catalogos/Contratistas/Index.php`
- [ ] T024 [US3] Componente Volt `app/Livewire/Catalogos/Contratistas/Form.php`
- [ ] T025 [US3] Ruta `/contratistas` protegida por `ContratistaPolicy`

**Checkpoint**: Los 3 catálogos maestros son funcionales de forma independiente

---

## Phase 6: Polish & Cross-Cutting Concerns

- [ ] T026 [P] Seeder `database/seeders/CatalogosMaestrosSeeder.php` con datos de ejemplo (clientes,
  proveedores, contratistas de prueba)
- [ ] T027 Añadir navegación de los 3 catálogos al layout principal (menú, visible solo para roles
  autorizados)
- [ ] T028 Ejecutar y verificar `php artisan test --filter=Catalogos` en verde

---

## Dependencies & Execution Order

- **Setup (Phase 1)** → **Foundational (Phase 2)** bloquea todo lo demás.
- **US1, US2, US3 (Fases 3-5)** son independientes entre sí una vez completada la Fase 2 — pueden
  desarrollarse en paralelo (son 3 entidades sin relación entre ellas).
- **Polish (Phase 6)** depende de que las 3 historias estén completas.

## Implementation Strategy

MVP = User Story 1 (Cliente) sola, ya que es el bloqueante más urgente para spec 002 (OT). US2 y US3 pueden
seguir en paralelo o inmediatamente después sin reordenar nada ya construido.
