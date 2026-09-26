# Tasks: Reportes e Indicadores (KPIs)

**Input**: Design documents from `specs/007-reportes-kpis/` (plan.md, spec.md)

**Prerequisites**: specs 002, 003, 004, 006 con su modelo de datos ya implementado (este módulo es de solo
lectura/agregación, no tiene sentido antes de que existan datos que agregar)

**Tests**: Incluidos — cada indicador se verifica contra un valor esperado conocido (SC-003).

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 ~~`composer require maatwebsite/excel`~~ — no instalable con PHP 8.2 +
  `phpoffice/phpspreadsheet ^5.9` ya fijado en `composer.json` (todas las versiones de
  maatwebsite/excel compatibles exigen PHP 8.3 o un phpspreadsheet 1.x/^1.30 incompatible).
  Se usa `phpoffice/phpspreadsheet` directamente (ya es dependencia del proyecto, usada por
  `ImportarInventarioExcel`) vía `App\Services\Reportes\ExcelExportService`; dompdf ya instalado
  por spec 006, reutilizado igual.

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

- [X] T013 Los indicadores agregados se muestran directamente en `livewire/dashboard.blade.php` (Phase 3)
  en vez de una pantalla `IndicadoresAgregados` separada. **Decisión (2026-09-26)**: se descarta la
  pantalla propia — US3 (exportación) terminó implementándose sobre el tablero de OT y el catálogo de
  inventario, no sobre los indicadores agregados, así que ya no hay motivo pendiente para evaluarlo; el
  dashboard con `wire:poll` cubre la necesidad de Administrador/Jefe de Taller.
- [X] T014 [US2] ~~Ruta `/reportes/indicadores`~~ — descartada junto con T013 (2026-09-26): no se
  construye, el dashboard existente es suficiente.

**Checkpoint**: US1 + US2 cubiertos por el mismo dashboard, sin pantalla propia (decisión de alcance
2026-09-26) ✅

---

## Phase 5: User Story 3 - Exportación y filtros avanzados (Priority: P2)

**Goal**: Exportar a Excel/PDF los listados de OT e Inventario (primer corte), con filtros avanzados

**Independent Test**: Filtrar listado de OT por estado+fecha, exportar a Excel y PDF, verificar que el
archivo coincide exactamente con lo filtrado en pantalla

### Tests for User Story 3

- [X] T015 [P] [US3] Feature test `tests/Feature/Reportes/ExportacionTest.php`: Excel y PDF de OT con
  filtros aplicados
- [X] T016 [P] [US3] Feature test: exportación de Inventario con filtros (mismo archivo)

### Implementation for User Story 3

- [X] T017 [US3] ~~`app/Exports/OrdenesTrabajoExport.php` (maatwebsite/excel)`~~ — sin Export classes
  aparte: `otsFiltradas()` (query privada ya compartida con la paginación) alimenta
  `App\Services\Reportes\ExcelExportService::descargar()` directamente desde
  `resources/views/livewire/ordenes-trabajo/tablero.blade.php`, para que el Excel nunca pueda
  desalinearse de lo que se ve filtrado en pantalla. PDF vía `resources/views/pdf/ordenes-trabajo.blade.php`.
- [X] T018 [P] [US3] Mismo patrón para inventario: `itemsFiltrados()` en
  `resources/views/livewire/inventario/catalogo.blade.php` + `resources/views/pdf/inventario.blade.php`.
- [X] T019 [US3] ~~Componente Volt `app/Livewire/Reportes/Exportador.php`~~ — sin componente/página
  aparte: los botones "Exportar Excel"/"Exportar PDF" viven en el propio tablero de OT y catálogo de
  inventario (junto a sus filtros existentes), no en una pantalla nueva — así el export siempre usa
  exactamente el filtro que el usuario ya tiene activo, sin duplicar la UI de filtros. `tablero.blade.php`
  ganó además los filtros de **técnico** y **rango de fechas** que pedía FR-007 y que aún no existían ahí.

**Checkpoint**: Las 3 historias de usuario son funcionales de forma independiente

---

## Phase 5b: User Story 4 - Auditoría de actividad reciente (Priority: P3)

**Documentado retroactivamente (2026-09-26)**: implementado en los commits `66eb2f4`/`cff962b`
(2026-09-25/26) sin pasar por `/speckit-tasks` en su momento.

- [X] T022 [US4, FR-009] Drill-downs del panel de operación: `livewire/reportes/panel-operacion.blade.php`
  (embebido en `dashboard.blade.php` para Administrador/Jefe de Taller) — cada tarjeta agregada abre un
  modal con el listado real (OT por categoría, herramientas pendientes, préstamos sin devolver, stock
  bajo, técnicos libres/ocupados, OT estancadas, despachos pendientes) vía `abrirOt()`/`abrirSimple()`.
- [X] T023 [US4, FR-009] Drill-down de desempeño inline: `abrirDesempeno()`/`verTecnico()` en el mismo
  componente, reusando `DesempenoTecnicoService` (spec 004, US3) sin duplicar el cálculo.
- [X] T024 [US4, FR-010] Pantalla `livewire/reportes/auditoria.blade.php`, ruta `/reportes/auditoria`
  (`middleware('role:Administrador')`, `routes/web.php`), permiso `view-auditoria`: unifica `ot_eventos`
  (spec 002) de todas las OT con filtros por tipo, usuario y rango de fechas (`#[Url]` en los 4 campos),
  paginado (`WithPagination`). Sin tabla nueva.
- [X] T025 [US4] Tests: `tests/Feature/Reportes/PanelOperacionTest.php` (drill-downs, permisos por rol) y
  cobertura de auditoría en la suite de Reportes.

**Checkpoint**: US4 funcional de forma independiente — no depende de US1-US3 en código aunque comparte
`IndicadoresAgregadosService`/`DesempenoTecnicoService`.

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
