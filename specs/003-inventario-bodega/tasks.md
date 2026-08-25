# Tasks: Gestión de Inventario (Bodega)

**Input**: Design documents from `specs/003-inventario-bodega/` (plan.md, spec.md)

**Prerequisites**: spec 000 (Proveedor) implementado; spec 002 (OT) recomendado en paralelo para probar la
integración de solicitudes de insumo

**Tests**: Incluidos — atomicidad de stock y flujo de auditoría con aprobación son reglas críticas.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 Instalar librería de código de barras (`composer require picqer/php-barcode-generator`) — PHP
  puro, sin extensiones nativas, verificado funcionando (endpoint `/inventario/{item}/etiqueta`)

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Bloquea las 5 historias de usuario

- [X] T002 Migración `create_categorias_inventario_table`
- [X] T003 [P] Migración `create_inventario_table` (incluye `ubicacion`, `codigo_barras`,
  `estado_herramienta`)
- [X] T004 [P] Migración `create_movimientos_inventario_table` (incluye `proveedor_id`, `origen`,
  `cliente_id`)
- [X] T005 [P] Migración `create_auditorias_inventario_table`
- [X] T006 [P] Migración `create_ajustes_auditoria_table`
- [X] T007 Seeder `database/seeders/CategoriasInventarioSeeder.php` — siembra las 7 categorías reales
  mapeadas a los 4 prefijos base (ver comentario en el seeder: el mapeo exacto no estaba definido en la
  spec/plan, se agrupó por afinidad Repuestos/Llantas/Tuberías→REP-, Herramientas→HER-,
  Insumos/EPP/Pinturas→CON-)
- [X] T008 Modelos Eloquent: `Inventario`, `CategoriaInventario`, `MovimientoInventario`,
  `AuditoriaInventario`, `AjusteAuditoria`
- [X] T009 `app/Services/Inventario/CodigoInternoService.php` (prefijo + consecutivo por categoría, FR-011)
- [X] T010 `app/Services/Inventario/MovimientoService.php` (mutación de stock centralizada, con
  `lockForUpdate()` para atomicidad — edge case de concurrencia del spec)
- [X] T011 `app/Services/Inventario/BarcodeService.php` (wrapper sobre picqer/php-barcode-generator)
- [X] T012 `app/Policies/InventarioPolicy.php` y `app/Policies/AuditoriaPolicy.php` (`approve()` solo
  Administrador, vía permiso `approve-auditorias-inventario`)

**Checkpoint**: Modelo de datos + servicios de stock atómico listos

---

## Phase 3: User Story 1 - Atender solicitudes de insumos generadas desde una OT (Priority: P1) — DIFERIDA

> **Nota de alcance (2026-08-25)**: El flujo "pendiente → aprobada → entregada" de una solicitud generada
> por una tarea de OT vive en el estado de `DETALLE_OT` (spec 002), que todavía no existe — no hay una
> tabla propia de "solicitud de insumo" en este módulo (ver Data Model del plan). Construir la UI de
> aprobación ahora significaría adivinar ese estado y probablemente rehacerla — mismo criterio aplicado en
> specs 004 y 005. La mecánica de descuento de stock que este flujo necesitará (`MovimientoService::salida`,
> con `origen: 'ot'`) ya está lista y probada (T013-T015 cubiertas por `MovimientoTest.php`, usando `origen:
> 'manual'` como sustituto de prueba); falta únicamente la pantalla de aprobación de spec 002 que la invoque
> con `origen: 'ot'` y una referencia a la tarea.

**Goal**: Aprobar/entregar solicitudes de insumo desde OT, descuento de stock, cambio a "En uso" si es
herramienta

**Independent Test**: Con una solicitud pendiente (real o simulada), aprobar y entregar, verificar
descuento correcto

### Tests for User Story 1

- [X] T013 [P] [US1] Feature test `tests/Feature/Inventario/MovimientoTest.php`: aprobar → entregar →
  descuento de stock (mecánica de `MovimientoService::salida` cubierta; la "aprobación" propia de OT queda
  pendiente de spec 002)
- [X] T014 [P] [US1] Feature test: bloqueo/advertencia si stock insuficiente (FR-009)
- [X] T015 [P] [US1] Feature test: entrega de herramienta cambia su estado a "En uso"

### Implementation for User Story 1

- [ ] T016 [US1] ~~Componente Volt `Tablero` (Ilustración 17: solicitudes desde OT + manuales)~~ — diferido;
  la mitad de solicitudes manuales quedó cubierta en `inventario.movimientos` (ver Fase 4)
- [X] T017 [US1] Acciones "Aprobar"/"Entregar" invocando `MovimientoService` — mecánica lista
  (`MovimientoService::salida`), pendiente de conectar a la UI de OT cuando exista
- [ ] T018 [US1] ~~Ruta `/inventario/solicitudes`~~ — la ruta ya existe pero sirve `inventario.movimientos`
  (solo manuales); se ampliará con la columna "desde OT" cuando spec 002 esté lista

**Checkpoint**: Mecánica de stock lista; falta la UI de aprobación propia de OT (bloqueada por spec 002)

---

## Phase 4: User Story 2 - Registrar solicitud manual de insumos sin OT (Priority: P1)

**Goal**: Salida directa a cliente externo sin OT, con trazabilidad propia

**Independent Test**: Registrar salida manual con cliente/insumo/cantidad, verificar descuento y
distinción clara de las solicitudes vía OT

### Tests for User Story 2

- [X] T019 [P] [US2] Feature test `tests/Feature/Inventario/SolicitudManualTest.php`: registro sin OT
  asociada, `origen = manual`

### Implementation for User Story 2

- [X] T020 [US2] Componente Volt `inventario.movimientos` (Ilustración 18) —
  `resources/views/livewire/inventario/movimientos.blade.php`
- [X] T021 [US2] Selector de `Cliente` (spec 000) en el formulario manual

**Checkpoint**: US2 funcional de forma independiente ✅ (US1 diferida, ver nota arriba)

---

## Phase 5: User Story 3 - Devolución y control de estado de herramientas (Priority: P2)

**Goal**: Registrar devolución con evaluación de estado (disponible/dañada/en mantenimiento)

**Independent Test**: Marcar herramienta "En uso" como devuelta, verificar transición de estado correcta

### Tests for User Story 3

- [X] T022 [P] [US3] Feature test `tests/Feature/Inventario/DevolucionHerramientaTest.php`: devolución en
  buen estado → Disponible; en mal estado → Dañada/En mantenimiento (SC-004)

### Implementation for User Story 3

- [X] T023 [US3] Componente Volt — integrado en `inventario.movimientos` (tabla "Herramientas pendientes de
  devolución" + acción inline), no como componente separado

**Checkpoint**: US2 + US3 funcionan de forma independiente ✅

---

## Phase 6: User Story 4 - Alertas de stock mínimo y auditoría de inventario (Priority: P2)

**Goal**: Alerta de stock bajo; auditoría bajo demanda con ajuste pendiente de aprobación del Administrador

**Independent Test**: Reducir stock bajo el mínimo (dispara alerta); iniciar auditoría, registrar
diferencia, aprobar/rechazar como Administrador

### Tests for User Story 4

- [X] T024 [P] [US4] Feature test `tests/Feature/Inventario/AuditoriaAjusteTest.php` +
  `AuditoriaFlujoTest.php`: ajuste pendiente no modifica `stock_actual` hasta aprobación (doble validación)
- [X] T025 [P] [US4] Feature test: evento de stock bajo se dispara en el mismo movimiento que lo origina
  (capturable con `Event::fake()`, consumido por spec 008)

### Implementation for User Story 4

- [X] T026 [US4] Evento `StockBajo` disparado desde `MovimientoService` cuando `stock_actual < stock_minimo`
- [X] T027 [US4] Componente Volt `inventario.auditoria` (iniciar, registrar conteo, ver pendientes)
- [X] T028 [US4] Acción de aprobación/rechazo de ajuste restringida a Administrador (`AuditoriaPolicy`)

**Checkpoint**: US2-US4 funcionan de forma independiente ✅ (US1 diferida)

---

## Phase 7: User Story 5 - Codificación, ubicación física y código de barras (Priority: P2)

**Goal**: Categoría real, código interno con prefijo, ubicación `Pasillo-Estante-Nivel`, código de barras
Code128 imprimible y escaneable

**Independent Test**: Registrar ítem, verificar código autogenerado, ubicación validada, generar e
"imprimir" código de barras, simular escaneo en un formulario de movimiento

### Tests for User Story 5

- [X] T029 [P] [US5] Feature test `tests/Feature/Inventario/CodificacionUbicacionTest.php` +
  `CatalogoTest.php`: código con prefijo correcto por categoría, validación de formato de ubicación,
  advertencia (no bloqueo) en colisión de ubicación

### Implementation for User Story 5

- [X] T030 [US5] Componente Volt `inventario.catalogo` (CRUD de ítems con categoría y ubicación)
- [X] T031 [US5] Acción "Generar etiqueta" — `InventarioEtiquetaController` sirve el PNG vía `BarcodeService`
  (`GET /inventario/{inventario}/etiqueta`)
- [ ] T032 [US5] ~~Campo de búsqueda por código en formularios de movimiento (lector USB HID + Enter)~~ —
  diferido: no hay todavía un formulario de movimiento de OT donde aplique un lector de código de barras
  (depende de spec 002); el campo de búsqueda por texto en `inventario.catalogo` ya filtra por `codigo`

**Checkpoint**: US2-US5 funcionan de forma independiente ✅ (US1 diferida, ver nota de alcance en Fase 3)

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T033 [P] Índices en `codigo` (unique), `categoria_id`, `ubicacion` de la tabla `inventario`
- [X] T034 Registrar `proveedor_id` (spec 000) en movimientos de tipo entrada (FR-014) —
  `MovimientoService::entrada()`
- [X] T035 Ejecutar `php artisan test --filter=Inventario` en verde (22/22)
- [X] T036 (añadida 2026-08-25, FR-015) Pantalla propia de gestión de Categoría de Inventario y Unidad de
  Medida (`inventario.catalogos`, ruta `/inventario/categorias`, pestaña "Categorías y unidades") — crear/
  editar/inactivar ambos catálogos sin afectar ítems ya existentes; `unidad_medida` dejó de ser texto libre
  y pasó a `unidad_medida_id` (FK a la nueva tabla `unidades_medida`). Tests: `CatalogosMaestrosTest.php`
  (7/7)

---

## Phase 9: Costeo por lotes (FIFO), cancelación de auditoría y dashboard (añadida 2026-08-25, FR-016 a FR-018)

> **Nota de alcance**: el costeo pasó por tres versiones el mismo día antes de llegar a la correcta — ver
> Clarifications. T040 describe solo la versión final (lotes + FIFO); las dos anteriores (promedio
> ponderado, luego "costo de la última entrada" reemplazando un único campo) se descartaron por completo,
> incluidos sus tests, antes de llegar a T044.

- [X] T037 [P] Migración `add_costo_unitario_to_movimientos_inventario_table` — costo real de cada
  movimiento (entrada: costo del lote; salida: costo de los lotes consumidos)
- [X] T038 [P] Migración `add_estado_to_auditorias_inventario_table` (`abierta`/`cerrada`/`cancelada`,
  default `abierta`; backfill de auditorías ya cerradas por `fecha_cierre`)
- [X] T039 `app/Support/Moneda.php` — helper único de formato de pesos colombianos (`$ 16.000`), usado en
  todo el módulo (ver spec 009, FR-001)
- [X] T040 [FR-016] Costeo por lotes con consumo FIFO:
  - Migración `add_cantidad_disponible_to_movimientos_inventario_table` — cada entrada es su propio lote
  - Migración `add_ajuste_auditoria_to_movimientos_inventario_origen` (ALTER del enum `origen`)
  - Migración de datos `backfill_lotes_movimientos_inventario` — reconstruye `cantidad_disponible` de las
    entradas ya existentes descontando FIFO el consumo histórico; crea un lote de "saldo inicial" para el
    stock sin entrada propia (ítems importados directo del Excel). Verificado en la BD real: 253/253 ítems
    consumibles con Σ lotes == `stock_actual` tras el backfill
  - `Inventario::lotesDisponibles()` (relación) y `Inventario::valorTotal()` recalculado sumando lotes
  - `MovimientoService::consumirLotesFifo()` — consume el lote más antiguo primero; `salida()` lo usa para
    consumibles y registra en el movimiento el costo exacto de los lotes tocados; `entrada()` crea un lote
    nuevo por cada entrada; `aplicarAjusteAprobado()` consume lotes en un faltante y crea uno nuevo en un
    sobrante (al costo de referencia del ítem)
  - Campo "Costo unitario" retirado del formulario de catálogo (ya no editable a mano, sin leyenda
    explicativa — sobraba); dashboard y listado de catálogo recalculados desde los lotes, no desde
    `stock_actual × costo_unitario`
- [X] T041 [FR-016] Formulario "Registrar entrada" en `inventario.movimientos` (ítem, proveedor, cantidad,
  costo de esta entrada) + tabla "Entradas recientes"; columna "Costo" agregada a "Solicitudes manuales"
  (costo exacto de los lotes que consumió esa salida)
- [X] T042 [FR-017] `AuditoriaInventario::cancelarAuditoria()` — marca `cancelada` y rechaza en cascada los
  ajustes pendientes de esa auditoría
- [X] T043 [FR-018] Componente Volt `inventario.dashboard` en `/inventario` (catálogo se movió a
  `/inventario/catalogo`): tarjetas de ítems activos, valor real del inventario (Σ lotes), bajo stock
  mínimo, herramientas por estado; tablas de ítems críticos y valor por categoría
- [X] T044 [P] Tests: consumo FIFO de un lote y de varios lotes con costo exacto, valor total por lotes,
  ajuste de auditoría con faltante/sobrante (`MovimientoTest.php`, `AuditoriaAjusteTest.php`), cancelación
  de auditoría (`AuditoriaFlujoTest.php`), dashboard con valores por lotes (`DashboardTest.php`), formato de
  moneda (`tests/Unit/Support/MonedaTest.php`) — 92/92 en verde

**Checkpoint**: Costeo real de inventario listo para que spec 002 coste OT con el valor vigente en cada
consumo; ver spec 009 para el estándar transversal de moneda y listas desplegables que nació de esta
misma corrección.

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
