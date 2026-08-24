# Tasks: Gestión de Personal

**Input**: Design documents from `specs/004-gestion-personal/` (plan.md, spec.md)

**Prerequisites**: spec 001 (usuarios con rol Técnico) implementado

**Tests**: Incluidos — cálculos de desempeño requieren verificación contra datos conocidos.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup (Shared Infrastructure)

- [ ] T001 Confirmar que existen usuarios con rol Técnico de prueba (spec 001) en el entorno local

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Bloquea las 3 historias de usuario

- [ ] T002 Migración `xxxx_create_especialidades_table.php`
- [ ] T003 [P] Migración `xxxx_create_tecnicos_table.php` (`usuario_id` unique, `especialidad_id`,
  `tarifa_hora`, `activo`)
- [ ] T004 Seeder `database/seeders/EspecialidadesSeeder.php` (catálogo fijo inicial: Eléctrico, Mecánico,
  Refrigeración, etc. — a validar lista final con el cliente en implementación)
- [ ] T005 Modelos Eloquent: `Tecnico`, `Especialidad`
- [ ] T006 `app/Policies/TecnicoPolicy.php` (gestión restringida a Administrador)

**Checkpoint**: Modelo de datos listo — las historias de usuario pueden implementarse

---

## Phase 3: User Story 1 - Registrar y configurar trabajadores (Priority: P1) 🎯 MVP

**Goal**: Ficha de técnico vinculada a usuario existente, con especialidad de catálogo fijo y estado activo

**Independent Test**: Crear técnico vinculado a usuario con rol Técnico, verificar disponibilidad en
selector de operario de OT (o un selector de prueba si spec 002 aún no existe)

### Tests for User Story 1

- [ ] T007 [P] [US1] Feature test `tests/Feature/Personal/TecnicoTest.php`: creación válida vinculada a
  usuario existente
- [ ] T008 [P] [US1] Feature test: técnico inactivo excluido de selectores de asignación (FR-002)

### Implementation for User Story 1

- [ ] T009 [US1] Componente Volt `app/Livewire/Personal/Tecnicos.php` (listado + form crear/editar/activo)
- [ ] T010 [US1] Ruta `/personal/tecnicos` protegida por `TecnicoPolicy`

**Checkpoint**: Ficha de técnico funcional — MVP del módulo (prerrequisito de spec 002)

---

## Phase 4: User Story 2 - Asignación de trabajadores a Órdenes de Trabajo (Priority: P1)

**Goal**: Visibilidad de carga actual (tareas activas) de cada técnico al momento de asignar

**Independent Test**: Con dos técnicos (uno con 3 tareas activas, otro con 0 — datos de prueba si spec 002
aún no está completo), verificar que la carga es visible/consultable

### Tests for User Story 2

- [ ] T011 [P] [US2] Feature test `tests/Feature/Personal/CargaTecnicoTest.php`: cálculo de tareas activas
  por técnico con datos de OT de prueba

### Implementation for User Story 2

- [ ] T012 [US2] Método `Tecnico::tareasActivasCount()` (o scope) consultando `detalle_ot` (spec 002)
- [ ] T013 [US2] Exponer la carga en el selector de operario del formulario de OT (integración con spec
  002 — coordinar con `Livewire/OrdenesTrabajo/Crear.php`)

**Checkpoint**: US1 + US2 funcionan de forma independiente

---

## Phase 5: User Story 3 - Medir desempeño del personal (Priority: P2)

**Goal**: Reporte de tiempos de ejecución, participación en OT y productividad por técnico

**Independent Test**: Con historial de OT de prueba, generar reporte y verificar manualmente el comparativo
estimado vs. real y conteo de OT

### Tests for User Story 3

- [ ] T014 [P] [US3] Feature test `tests/Feature/Personal/DesempenoTecnicoTest.php`: cálculo de tiempo
  promedio de ejecución, número de OT/tareas, comparativo estimado vs. real con datos conocidos (SC-003)

### Implementation for User Story 3

- [ ] T015 [US3] `app/Services/Personal/DesempenoTecnicoService.php` — agregación SQL sobre `detalle_ot`/
  `ordenes_trabajo` (spec 002), **aislado de la UI para reutilizarse en spec 007**
- [ ] T016 [US3] Componente Volt `app/Livewire/Personal/Desempeno.php` (reporte con filtro de fechas)
- [ ] T017 [US3] Ruta `/personal/desempeno`

**Checkpoint**: Las 3 historias de usuario son funcionales de forma independiente

---

## Phase 6: Polish & Cross-Cutting Concerns

- [ ] T018 Verificar que las métricas de un técnico inactivado se conservan (no se filtran del histórico,
  solo de selectores de asignación — FR-006)
- [ ] T019 Ejecutar `php artisan test --filter=Personal` en verde

---

## Dependencies & Execution Order

- **Setup + Foundational** bloquean todo.
- **US1** es el MVP real — sin ficha de técnico, spec 002 no puede asignar operarios reales.
- **US2** depende de que exista `detalle_ot` (spec 002) con datos, pero el método de conteo puede
  desarrollarse y testearse con datos de prueba antes de que spec 002 esté 100% completo.
- **US3** depende de historial real de OT para ser útil, pero es igualmente testeable con datos de prueba.

## Implementation Strategy

MVP = User Story 1 sola — es el bloqueante más urgente para spec 002 ("si no tengo el operario con su
especialidad no puedo crear una orden"). US2 y US3 pueden seguir en cualquier orden tras completar US1.
