# Tasks: Reportes e Indicadores (KPIs)

**Input**: Design documents from `specs/007-reportes-kpis/` (plan.md, spec.md)

**Prerequisites**: specs 002, 003, 004, 006 con su modelo de datos ya implementado (este módulo es de solo
lectura/agregación, no tiene sentido antes de que existan datos que agregar)

**Tests**: Incluidos — cada indicador se verifica contra un valor esperado conocido (SC-003).

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup (Shared Infrastructure)

- [ ] T001 `composer require maatwebsite/excel` (barryvdh/laravel-dompdf ya instalado por spec 006 —
  reutilizar, no reinstalar) — diferido a la implementación de US3 (exportación)

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Bloquea las 3 historias de usuario

- [x] T002 `app/Services/Reportes/IndicadoresAgregadosService.php` — `otResumen()` (abiertas/cerradas/
  vencidas/próximas a vencer), `cumplimientoTiempos()`, `productividadEquipo()` (delega en
  `DesempenoTecnicoService`, spec 004) y `tareasEnEjecucion()` (Módulo 1, "control de tiempos")
- [~] T003-T005 Servicios `DashboardAdministradorService`/`DashboardJefeTallerService`/
  `DashboardAlmacenistaService` — **no se crearon como clases separadas**: Administrador y Jefe de Taller
  comparten el mismo bloque de indicadores (`IndicadoresAgregadosService`) directamente en
  `livewire/dashboard.blade.php`; Almacenista ya tenía su propio dashboard operativo en `/inventario`
  (`livewire/inventario/dashboard.blade.php`, spec 003) con sus indicadores de FR-001 escenario 3 — no se
  duplica
- [ ] T006 `app/Policies/ReportePolicy.php` — no fue necesario: el filtrado por rol se resolvió con
  `hasAnyRole()` en el propio componente `dashboard.blade.php`, siguiendo el patrón ya usado en el resto
  del módulo (`Gate::authorize` en `mount()` de cada componente)

**Checkpoint**: Servicio de agregación listo — las historias de usuario pueden implementarse

---

## Phase 3: User Story 1 - Dashboard en tiempo real con indicadores clave (Priority: P1) 🎯 MVP

**Goal**: Dashboard por rol al ingresar, calculado por consulta directa + auto-refresh (`wire:poll`, ver
Clarifications 2026-09-15)

**Independent Test**: Con datos de OT/inventario/cotizaciones de prueba, verificar que el dashboard de cada
rol refleja las cifras correctas

### Tests for User Story 1

- [x] T007 [P] [US1] Feature test `tests/Feature/Reportes/DashboardPorRolTest.php`: Jefe de Taller y
  Administrador ven los indicadores; Vendedor (sin dashboard aún) sigue viendo el placeholder

### Implementation for User Story 1

- [~] T008-T010 Dashboards de Administrador/Jefe de Taller implementados como un único bloque condicional
  dentro de `livewire/dashboard.blade.php` (rol vía `hasAnyRole(['Administrador','Jefe de Taller'])`), con
  `wire:poll.20s`; Almacenista cubierto por `/inventario` (ver Phase 2). **Pendiente**: separar en
  componentes propios si los mockups (Ilustraciones 5/12/17) exigen layouts distintos por rol — por ahora
  ambos roles necesitan las mismas cifras operativas
- [x] T011 [US1] La ruta `/` ya resuelve el dashboard correcto según el rol autenticado dentro del mismo
  componente Volt `dashboard`

**Checkpoint**: Dashboard de indicadores funcional para Administrador y Jefe de Taller — MVP del módulo
cubierto para los dos roles con control de tiempos

---

## Phase 4: User Story 2 - Indicadores agregados de operación (Priority: P1)

**Goal**: OT abiertas/cerradas/vencidas, cumplimiento de tiempos, productividad del equipo, estado del
inventario

**Independent Test**: Con OT de prueba en distintos estados y tiempos, verificar cálculo correcto del
indicador de cumplimiento de tiempos

### Tests for User Story 2

- [x] T012 [P] [US2] Feature test `tests/Feature/Reportes/IndicadoresAgregadosTest.php`: un caso por
  indicador (OT abiertas/cerradas/vencidas, cumplimiento, productividad, trabajo en ejecución) contra
  valores conocidos. Estado de inventario no se testeó aquí — ya cubierto por
  `tests/Feature/Inventario/*DashboardTest.php` (spec 003)

### Implementation for User Story 2

- [~] T013 Los indicadores agregados se muestran directamente en `livewire/dashboard.blade.php` (Phase 3)
  en vez de una pantalla `IndicadoresAgregados` separada — **pendiente evaluar** si se necesita una vista
  propia con filtro de rango de fechas cuando se aborde exportación (US3)
- [ ] T014 [US2] Ruta `/reportes/indicadores` — diferida junto con T013

**Checkpoint**: US1 + US2 cubiertos por el mismo dashboard; falta decidir si ameritan pantalla propia

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

- [x] T020 Verificar que ningún indicador usa caché ni WebSockets (FR-008 actualizado 2026-09-15): el único
  mecanismo de refresco es `wire:poll` recalculando por consulta directa — revisado en
  `livewire/dashboard.blade.php`
- [x] T021 Ejecutar `php artisan test --filter=Reportes` en verde (8 tests)

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
