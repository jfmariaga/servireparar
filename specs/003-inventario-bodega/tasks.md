# Tasks: Gestión de Inventario (Bodega)

**Input**: Design documents from `specs/003-inventario-bodega/` (plan.md, spec.md)

**Prerequisites**: spec 000 (Proveedor) implementado; spec 002 (OT) recomendado en paralelo para probar la
integración de solicitudes de insumo

**Tests**: Incluidos — atomicidad de stock y flujo de auditoría con aprobación son reglas críticas.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup (Shared Infrastructure)

- [ ] T001 Instalar librería de código de barras (`composer require picqer/php-barcode-generator`) — PHP
  puro, sin extensiones nativas, verificar que funciona en Laragon local

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Bloquea las 5 historias de usuario

- [ ] T002 Migración `xxxx_create_categorias_inventario_table.php`
- [ ] T003 [P] Migración `xxxx_create_inventario_table.php` (incluye `ubicacion`, `codigo_barras`,
  `estado_herramienta`)
- [ ] T004 [P] Migración `xxxx_create_movimientos_inventario_table.php` (incluye `proveedor_id`, `origen`,
  `cliente_id`)
- [ ] T005 [P] Migración `xxxx_create_auditorias_inventario_table.php`
- [ ] T006 [P] Migración `xxxx_create_ajustes_auditoria_table.php`
- [ ] T007 Seeder `database/seeders/CategoriasInventarioSeeder.php` — siembra Llantas, EPP, Tuberías y
  Láminas, Insumos, Pinturas, Herramientas, Repuestos con su `prefijo_codigo`
- [ ] T008 Modelos Eloquent: `Inventario`, `CategoriaInventario`, `MovimientoInventario`,
  `AuditoriaInventario`, `AjusteAuditoria`
- [ ] T009 `app/Services/Inventario/CodigoInternoService.php` (prefijo + consecutivo por categoría, FR-011)
- [ ] T010 `app/Services/Inventario/MovimientoService.php` (mutación de stock centralizada, con
  `lockForUpdate()` para atomicidad — edge case de concurrencia del spec)
- [ ] T011 `app/Services/Inventario/BarcodeService.php` (wrapper sobre picqer/php-barcode-generator)
- [ ] T012 `app/Policies/InventarioPolicy.php` y `app/Policies/AuditoriaPolicy.php` (`approve()` solo
  Administrador)

**Checkpoint**: Modelo de datos + servicios de stock atómico listos

---

## Phase 3: User Story 1 - Atender solicitudes de insumos generadas desde una OT (Priority: P1) 🎯 MVP

**Goal**: Aprobar/entregar solicitudes de insumo desde OT, descuento de stock, cambio a "En uso" si es
herramienta

**Independent Test**: Con una solicitud pendiente (real o simulada), aprobar y entregar, verificar
descuento correcto

### Tests for User Story 1

- [ ] T013 [P] [US1] Feature test `tests/Feature/Inventario/MovimientoTest.php`: aprobar → entregar →
  descuento de stock
- [ ] T014 [P] [US1] Feature test: bloqueo/advertencia si stock insuficiente (FR-009)
- [ ] T015 [P] [US1] Feature test: entrega de herramienta cambia su estado a "En uso"

### Implementation for User Story 1

- [ ] T016 [US1] Componente Volt `app/Livewire/Inventario/Tablero.php` (Ilustración 17: solicitudes desde
  OT + manuales)
- [ ] T017 [US1] Acciones "Aprobar"/"Entregar" invocando `MovimientoService`
- [ ] T018 [US1] Ruta `/inventario/solicitudes`

**Checkpoint**: Flujo OT→Inventario funcional — MVP del módulo

---

## Phase 4: User Story 2 - Registrar solicitud manual de insumos sin OT (Priority: P1)

**Goal**: Salida directa a cliente externo sin OT, con trazabilidad propia

**Independent Test**: Registrar salida manual con cliente/insumo/cantidad, verificar descuento y
distinción clara de las solicitudes vía OT

### Tests for User Story 2

- [ ] T019 [P] [US2] Feature test `tests/Feature/Inventario/SolicitudManualTest.php`: registro sin OT
  asociada, `origen = manual`

### Implementation for User Story 2

- [ ] T020 [US2] Componente Volt `app/Livewire/Inventario/SolicitudManual.php` (Ilustración 18)
- [ ] T021 [US2] Selector de `Cliente` (spec 000) en el formulario manual

**Checkpoint**: US1 + US2 funcionan de forma independiente

---

## Phase 5: User Story 3 - Devolución y control de estado de herramientas (Priority: P2)

**Goal**: Registrar devolución con evaluación de estado (disponible/dañada/en mantenimiento)

**Independent Test**: Marcar herramienta "En uso" como devuelta, verificar transición de estado correcta

### Tests for User Story 3

- [ ] T022 [P] [US3] Feature test `tests/Feature/Inventario/DevolucionHerramientaTest.php`: devolución en
  buen estado → Disponible; en mal estado → Dañada/En mantenimiento (SC-004)

### Implementation for User Story 3

- [ ] T023 [US3] Componente Volt `app/Livewire/Inventario/Devolucion.php`

**Checkpoint**: US1-US3 funcionan de forma independiente

---

## Phase 6: User Story 4 - Alertas de stock mínimo y auditoría de inventario (Priority: P2)

**Goal**: Alerta de stock bajo; auditoría bajo demanda con ajuste pendiente de aprobación del Administrador

**Independent Test**: Reducir stock bajo el mínimo (dispara alerta); iniciar auditoría, registrar
diferencia, aprobar/rechazar como Administrador

### Tests for User Story 4

- [ ] T024 [P] [US4] Feature test `tests/Feature/Inventario/AuditoriaAjusteTest.php`: ajuste pendiente no
  modifica `stock_actual` hasta aprobación (doble validación)
- [ ] T025 [P] [US4] Feature test: evento de stock bajo se dispara en el mismo movimiento que lo origina
  (capturable con `Event::fake()`, consumido por spec 008)

### Implementation for User Story 4

- [ ] T026 [US4] Evento `StockBajo` disparado desde `MovimientoService` cuando `stock_actual < stock_minimo`
- [ ] T027 [US4] Componente Volt `app/Livewire/Inventario/Auditoria.php` (iniciar, registrar conteo, ver
  pendientes)
- [ ] T028 [US4] Acción de aprobación/rechazo de ajuste restringida a Administrador (`AuditoriaPolicy`)

**Checkpoint**: US1-US4 funcionan de forma independiente

---

## Phase 7: User Story 5 - Codificación, ubicación física y código de barras (Priority: P2)

**Goal**: Categoría real, código interno con prefijo, ubicación `Pasillo-Estante-Nivel`, código de barras
Code128 imprimible y escaneable

**Independent Test**: Registrar ítem, verificar código autogenerado, ubicación validada, generar e
"imprimir" código de barras, simular escaneo en un formulario de movimiento

### Tests for User Story 5

- [ ] T029 [P] [US5] Feature test `tests/Feature/Inventario/CodificacionUbicacionTest.php`: código con
  prefijo correcto por categoría, validación de formato de ubicación, advertencia (no bloqueo) en
  colisión de ubicación

### Implementation for User Story 5

- [ ] T030 [US5] Componente Volt `app/Livewire/Inventario/Catalogo.php` (CRUD de ítems con categoría y
  ubicación)
- [ ] T031 [US5] Acción "Generar etiqueta" que renderiza el código de barras vía `BarcodeService`
- [ ] T032 [US5] Campo de búsqueda por código en formularios de movimiento (captura input de lector USB
  HID + Enter)

**Checkpoint**: Las 5 historias de usuario son funcionales de forma independiente

---

## Phase 8: Polish & Cross-Cutting Concerns

- [ ] T033 [P] Índices en `codigo`, `categoria_id`, `ubicacion` de la tabla `inventario`
- [ ] T034 Registrar `proveedor_id` (spec 000) en movimientos de tipo entrada (FR-014)
- [ ] T035 Ejecutar `php artisan test --filter=Inventario` en verde

---

## Dependencies & Execution Order

- **Setup + Foundational** bloquean todo — `MovimientoService` (atomicidad) es prerrequisito de cualquier
  historia que mueva stock.
- **US1** es el MVP (integración crítica con OT).
- **US2, US3, US4** son independientes entre sí una vez completada la Fase 2.
- **US5** (codificación/ubicación/código de barras) es independiente funcionalmente pero se recomienda
  antes de US1-US4 en la práctica, ya que sin código interno los ítems no tienen identificador limpio — el
  orden de las fases aquí sigue la prioridad del spec, no una dependencia técnica estricta.

## Implementation Strategy

MVP = US1 (atender solicitudes desde OT), por ser la integración más crítica con spec 002. US5
(codificación/barras) puede desarrollarse en paralelo desde el inicio ya que solo depende de la Fase 2.
