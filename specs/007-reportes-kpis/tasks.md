# Tasks: Reportes e Indicadores (KPIs)

**Input**: Design documents from `specs/007-reportes-kpis/` (plan.md, spec.md)

**Prerequisites**: specs 002, 003, 004, 006 con su modelo de datos ya implementado (este módulo es de solo
lectura/agregación, no tiene sentido antes de que existan datos que agregar)

**Tests**: Incluidos — cada indicador se verifica contra un valor esperado conocido (SC-003).

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup (Shared Infrastructure)

- [ ] T001 `composer require maatwebsite/excel` (barryvdh/laravel-dompdf ya instalado por spec 006 —
  reutilizar, no reinstalar)

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Bloquea las 3 historias de usuario

- [ ] T002 `app/Services/Reportes/IndicadoresAgregadosService.php` — fórmulas documentadas en plan.md (OT
  abiertas/cerradas/vencidas, cumplimiento de tiempos, estado de inventario); delega productividad en
  `DesempenoTecnicoService` (spec 004)
- [ ] T003 [P] `app/Services/Reportes/DashboardAdministradorService.php`
- [ ] T004 [P] `app/Services/Reportes/DashboardJefeTallerService.php`
- [ ] T005 [P] `app/Services/Reportes/DashboardAlmacenistaService.php`
- [ ] T006 `app/Policies/ReportePolicy.php` (qué dashboard/indicador ve cada rol)

**Checkpoint**: Services de agregación listos — las historias de usuario pueden implementarse

---

## Phase 3: User Story 1 - Dashboard en tiempo real con indicadores clave (Priority: P1) 🎯 MVP

**Goal**: Dashboard por rol al ingresar, calculado por consulta directa (sin polling/caché)

**Independent Test**: Con datos de OT/inventario/cotizaciones de prueba, verificar que el dashboard de cada
rol refleja las cifras correctas

### Tests for User Story 1

- [ ] T007 [P] [US1] Feature test `tests/Feature/Reportes/DashboardPorRolTest.php`: un caso por rol
  (Administrador, Jefe de Taller, Almacenista) comparando cifras mostradas vs. conteo SQL directo

### Implementation for User Story 1

- [ ] T008 [US1] Componente Volt `app/Livewire/Reportes/DashboardAdministrador.php` (Ilustración 5)
- [ ] T009 [P] [US1] Componente Volt `app/Livewire/Reportes/DashboardJefeTaller.php` (Ilustración 12)
- [ ] T010 [P] [US1] Componente Volt `app/Livewire/Reportes/DashboardAlmacenista.php` (Ilustración 17)
- [ ] T011 [US1] Composición de la ruta `/` según prioridad de rol (reutiliza FR-010 de spec 001)

**Checkpoint**: Dashboards por rol funcionales — MVP del módulo

---

## Phase 4: User Story 2 - Indicadores agregados de operación (Priority: P1)

**Goal**: OT abiertas/cerradas/vencidas, cumplimiento de tiempos, productividad del equipo, estado del
inventario

**Independent Test**: Con OT de prueba en distintos estados y tiempos, verificar cálculo correcto del
indicador de cumplimiento de tiempos

### Tests for User Story 2

- [ ] T012 [P] [US2] Feature test `tests/Feature/Reportes/IndicadoresAgregadosTest.php`: un caso por
  indicador (OT abiertas/cerradas/vencidas, cumplimiento, inventario, productividad) contra valores
  conocidos

### Implementation for User Story 2

- [ ] T013 [US2] Componente Volt `app/Livewire/Reportes/IndicadoresAgregados.php` (vista consolidada con
  filtro de rango de fechas)
- [ ] T014 [US2] Ruta `/reportes/indicadores`

**Checkpoint**: US1 + US2 funcionan de forma independiente

---

## Phase 5: User Story 3 - Exportación y filtros avanzados (Priority: P2)

**Goal**: Exportar a Excel/PDF los listados de OT e Inventario (primer corte), con filtros avanzados

**Independent Test**: Filtrar listado de OT por estado+fecha, exportar a Excel y PDF, verificar que el
archivo coincide exactamente con lo filtrado en pantalla

### Tests for User Story 3

- [ ] T015 [P] [US3] Feature test `tests/Feature/Reportes/ExportacionTest.php`: Excel y PDF de OT con
  filtros aplicados
- [ ] T016 [P] [US3] Feature test: exportación de Inventario con filtros

### Implementation for User Story 3

- [ ] T017 [US3] `app/Exports/OrdenesTrabajoExport.php` (maatwebsite/excel)
- [ ] T018 [P] [US3] `app/Exports/InventarioExport.php`
- [ ] T019 [US3] Componente Volt `app/Livewire/Reportes/Exportador.php` (filtros + botones Excel/PDF)

**Checkpoint**: Las 3 historias de usuario son funcionales de forma independiente

---

## Phase 6: Polish & Cross-Cutting Concerns

- [ ] T020 Verificar que ningún indicador usa caché/polling (FR-008) — revisión de código, no solo test
- [ ] T021 Ejecutar `php artisan test --filter=Reportes` en verde

---

## Dependencies & Execution Order

- **Setup + Foundational** bloquean todo.
- **US1 (dashboards)** es el MVP, ya validado como patrón de UI en los mockups.
- **US2 (indicadores agregados)** es independiente de US1 en cuanto a cálculo, pero comparte los mismos
  Services de la Fase 2.
- **US3 (exportación)** depende de tener listados ya construidos (US1/US2 o los tableros de specs
  002/003 directamente) sobre los cuales aplicar filtros.

## Implementation Strategy

MVP = User Story 1 (dashboards por rol), por ser el patrón de UI ya validado en los 3 mockups reales. US3
(exportación de Compras/Personal) queda explícitamente para un corte posterior, según lo decidido en
`/speckit-clarify`.
