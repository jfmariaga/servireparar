# Tasks: Gestión de Equipos y Mantenimiento

**Input**: Design documents from `specs/005-equipos-mantenimiento/` (plan.md, spec.md)

**Prerequisites**: spec 000 (Cliente) y spec 002 (OT) implementados — el historial técnico depende de OT
de mantenimiento reales

**Tests**: Incluidos.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 Confirmar que existen Clientes (spec 000) de prueba en el entorno local — OT (spec 002) todavía
  no existe; ver nota de alcance debajo de Fase 3

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Bloquea las 4 historias de usuario

- [X] T002 Migración `create_equipos_table` (`cliente_id`, `periodicidad_mantenimiento_dias`)
- [ ] T003 [P] ~~Migración `create_variables_tecnicas_table`~~ — diferida (FK a `ordenes_trabajo`, spec 002
  inexistente; ver nota de alcance)
- [X] T004 [P] Migración `create_mantenimientos_preventivos_table` — sin FK a OT, no bloqueada
- [ ] T005 [P] ~~Migración `create_checklist_mantenimiento_table`~~ — diferida (FK a `ordenes_trabajo`)
- [X] T006 Modelos Eloquent: `Equipo`, `MantenimientoPreventivo` (`VariableTecnica`/`ChecklistMantenimiento`
  diferidos junto con sus migraciones)
- [X] T007 Evento `app/Events/MantenimientoPreventivoProximoAVencer.php` (consumido por spec 008)
- [X] T008 `app/Policies/EquipoPolicy.php` (registro: Administrador/Jefe de Taller, vía permiso
  `manage-equipos`; la habilidad de solo-lectura para Técnico se agrega junto con US2/historial)

**Checkpoint**: Modelo de datos listo — las historias de usuario pueden implementarse

---

## Phase 3: User Story 1 - Registrar equipos asociados a un cliente (Priority: P1) 🎯 MVP

**Goal**: CRUD de Equipo vinculado a Cliente (spec 000)

**Independent Test**: Con un cliente registrado, registrar un equipo y verificar disponibilidad en el
selector de equipos al crear una OT

### Tests for User Story 1

- [X] T009 [P] [US1] Feature test `tests/Feature/Equipos/RegistrarEquipoTest.php`: creación válida
  vinculada a cliente existente (la disponibilidad en el selector de OT se verifica cuando spec 002 exista)

### Implementation for User Story 1

- [X] T010 [US1] Componente Volt `equipos.index` (listado + form crear/editar) —
  `resources/views/livewire/equipos/index.blade.php`
- [X] T011 [US1] Ruta `/equipos` protegida por `EquipoPolicy`

**Checkpoint**: Registro de equipos funcional — MVP del módulo ✅

> **Nota de alcance (2026-08-25)**: Fase 4 (US2, historial técnico) y Fase 6 (US4, checklist) requieren
> `ordenes_trabajo`/`detalle_ot`/`evidencias_ot` (spec 002), que todavía no existen. Construirlas ahora
> significaría adivinar el esquema de spec 002 y muy probablemente rehacerlas después — mismo criterio
> aplicado en spec 004 con `detalle_ot`. Se difieren hasta que spec 002 esté implementada. Fase 5 (US3,
> mantenimiento preventivo) SÍ es independiente de spec 002 — se implementó completa en esta pasada.

---

## Phase 4: User Story 2 - Consultar historial técnico de un equipo (Priority: P1) — DIFERIDA

**Goal**: Historial reconstruido por consulta (OT + evidencias + variables técnicas + checklist)

**Independent Test**: Con un equipo con 2 OT de mantenimiento finalizadas, consultar su historial y
verificar que ambas aparecen con técnico, fecha y evidencias

### Tests for User Story 2

- [ ] T012 [P] [US2] Feature test `tests/Feature/Equipos/HistorialTecnicoTest.php`: reconstrucción completa
  sin fuentes externas (SC-001)
- [ ] T013 [P] [US2] Feature test `tests/Feature/Equipos/VariableTecnicaTest.php`: registro clave-valor
  asociado a una intervención, visible en el historial

### Implementation for User Story 2

- [ ] T014 [US2] `app/Services/Equipos/EquipoHistorialService.php` (agrega OT + evidencias + variables
  técnicas + checklist, evitando N+1 queries)
- [ ] T015 [US2] Componente Volt `app/Livewire/Equipos/Historial.php` (ficha técnica del equipo)
- [ ] T016 [US2] UI de registro de variables técnicas durante una OT de mantenimiento en curso (integración
  con `Livewire/OrdenesTrabajo/Detalle.php` de spec 002)
- [ ] T017 [US2] Ruta `/equipos/{equipo}/historial`

**Checkpoint**: US1 + US2 funcionan de forma independiente

---

## Phase 5: User Story 3 - Programar mantenimiento preventivo (Priority: P2) ✅

**Goal**: Cálculo automático de próxima fecha tras completar un mantenimiento, con alerta de vencimiento

**Independent Test**: Configurar periodicidad, completar un mantenimiento, verificar próxima fecha
calculada; forzar vencimiento y verificar disparo de alerta

### Tests for User Story 3

- [X] T018 [P] [US3] Feature test `tests/Feature/Equipos/MantenimientoPreventivoTest.php`: cálculo de
  próxima fecha = última + periodicidad
- [X] T019 [P] [US3] Feature test: evento `MantenimientoPreventivoProximoAVencer` disparado al vencer
  (`Event::fake()`, SC-002)

### Implementation for User Story 3

- [X] T020 [US3] `app/Services/Equipos/MantenimientoPreventivoService.php` (calcula próxima fecha, marca
  `alerta_disparada`)
- [X] T021 [US3] `app/Console/Commands/RevisarMantenimientosPreventivos.php`, registrado en el Scheduler
  (diario) — `routes/console.php`

**Checkpoint**: US1 y US3 funcionan de forma independiente ✅ (US2 diferida, ver nota arriba)

---

## Phase 6: User Story 4 - Checklist técnico digital (Priority: P2) — DIFERIDA

**Goal**: Completar checklist de plantilla única durante una intervención de mantenimiento

**Independent Test**: Completar checklist durante una OT de mantenimiento, verificar que queda asociado al
historial del equipo

### Tests for User Story 4

- [ ] T022 [P] [US4] Feature test `tests/Feature/Equipos/ChecklistMantenimientoTest.php`: resultado
  almacenado y visible en el historial

### Implementation for User Story 4

- [ ] T023 [US4] Componente Volt `app/Livewire/Equipos/ChecklistMantenimiento.php`
- [ ] T024 [US4] Seeder de ítems fijos del checklist técnico (plantilla única genérica)

**Checkpoint**: Las 4 historias de usuario son funcionales de forma independiente

---

## Phase 7: Polish & Cross-Cutting Concerns

- [ ] T025 Índice en `detalle_ot`/`ordenes_trabajo.equipo_id` para reconstrucción rápida del historial —
  diferido junto con spec 002
- [X] T026 Ejecutar `php artisan test --filter=Equipos` en verde (7/7 — US1 + US3)

---

## Dependencies & Execution Order

- **Setup + Foundational** bloquean todo.
- **US1** es el MVP — sin equipos registrados, spec 002 no puede asociar equipo a una OT.
- **US2** depende de que existan OT de mantenimiento finalizadas (spec 002) para tener datos reales, pero
  puede testearse con datos de prueba.
- **US3 y US4** son independientes entre sí y de US2, solo dependen de la Fase 2.

## Implementation Strategy

MVP = User Story 1 (registro de equipos), por ser prerrequisito de spec 002. US2 (historial) es el segundo
incremento por ser el valor diferencial central del módulo.
