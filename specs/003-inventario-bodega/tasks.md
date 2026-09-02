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

## Phase 10: User Story 6 - Venta mostrador: despacho sin OT con remisión firmada (Priority: P1) — añadida 2026-09-01 (FR-019 a FR-025)

> **Nota de alcance**: canal comercial de salida sin OT (spec 003, US6). Reutiliza `MovimientoService`
> (Fase 2) y `Moneda` (T039), ya listos y probados. **Depende de spec 001**: el rol `Vendedor` y el
> permiso `manage-despachos` se crean en `specs/001-autenticacion-usuarios/tasks.md` (Fase 8) — esa fase
> debe estar completa antes de T053/T065. La compra externa NO pasa por inventario (sin ítem, sin lote,
> sin movimiento): solo queda registrada en la línea de la solicitud.

**Goal**: Vendedor crea una Solicitud de Despacho con líneas → Almacenista la recibe, genera Remisión de
Entrega con consecutivo → cliente firma en pantalla → al confirmar la entrega se generan los movimientos de
salida (`origen: despacho`) de las líneas de inventario; las líneas de compra externa quedan trazadas.

**Independent Test**: Como Vendedor crear `SD-00001` con 2 líneas (un consumible con stock + una descrita a
mano como compra externa); como Almacenista recibir → remisionar (`REM-00001`) → firmar → entregar;
verificar que solo la línea de inventario generó movimiento y descuento FIFO, que la línea externa quedó
con proveedor/costo/motivo sin tocar inventario, y que la remisión PDF firmada es consultable.

### Setup for User Story 6

- [x] T045 Instalar `barryvdh/laravel-dompdf` (`composer require barryvdh/laravel-dompdf`) si no está ya
  presente — render server-side de la remisión imprimible

### Foundational for User Story 6

- [x] T046 [US6] Migración `create_solicitudes_despacho_table` en `database/migrations/` (`numero` unique,
  `cliente_id` FK, `vendedor_id` FK users, `estado` enum
  `borrador/solicitada/recibida/remisionada/entregada/anulada`, `observaciones`, timestamps de transición
  `recibida_por/recibida_en/remisionada_por/remisionada_en/entregada_en/anulada_por/motivo_anulacion`)
- [x] T047 [P] [US6] Migración `create_detalle_solicitud_despacho_table` (`solicitud_id` FK cascade,
  `origen` enum `inventario/compra_externa`, `inventario_id` FK nullable, `descripcion` nullable,
  `cantidad`, `costo_unitario` nullable, `proveedor_externo` nullable, `costo_compra_externa` nullable,
  `motivo` nullable, `movimiento_id` FK `movimientos_inventario` nullable)
- [x] T048 [P] [US6] Migración `create_remisiones_entrega_table` (`numero` unique, `solicitud_id` FK unique
  1:1, `generada_por` FK, `fecha`, `recibido_por_nombre`, `recibido_por_documento`, `firma` longText,
  `entregada_en` nullable)
- [x] T049 [US6] Migración `add_despacho_to_movimientos_inventario_origen` — ALTER del enum `origen` para
  añadir `despacho` (patrón de la migración `add_ajuste_auditoria_to_movimientos_inventario_origen`)
- [x] T050 [P] [US6] Modelos Eloquent `app/Models/SolicitudDespacho.php`,
  `app/Models/DetalleSolicitudDespacho.php`, `app/Models/RemisionEntrega.php` (relaciones, casts de fechas,
  scopes por estado, `SolicitudDespacho::detalles()`/`remision()`, `detallesInventario()` /
  `detallesCompraExterna()`)
- [x] T051 [P] [US6] `app/Services/Inventario/ConsecutivoDespachoService.php` — genera `SD-00001` /
  `REM-00001` (`str_pad` a 5, `max()` con `lockForUpdate()` dentro de la transacción de creación), patrón
  de `CodigoInternoService`
- [x] T052 [US6] `app/Services/Inventario/DespachoService.php` — `enviar()`, `recibir()`,
  `generarRemision()`, `confirmarEntrega()`, `anular()`; `confirmarEntrega()` es transaccional: por cada
  línea `origen=inventario` valida stock (lanza `StockInsuficienteException`, FR-009) y llama
  `MovimientoService::salida($item, $cant, $actor, origen: 'despacho', cliente: $solicitud->cliente,
  referencia: $remision->numero)`, fija `detalle.movimiento_id`; las líneas de compra externa no producen
  efecto en inventario; fija `remision.entregada_en` y `solicitud.entregada_en` (depende de T050, T051)
- [x] T053 [US6] `app/Policies/SolicitudDespachoPolicy.php` + registro en `AuthServiceProvider`:
  `create`/`update`/`anular` → Vendedor o Administrador; `recibir`/`remisionar`/`entregar` → Almacenista o
  Administrador; `anular` sólo si estado ∉ {`entregada`,`anulada`} (requiere permiso `manage-despachos` de
  spec 001, Fase 8)

### Tests for User Story 6

- [x] T054 [P] [US6] Feature test `tests/Feature/Despacho/CrearSolicitudDespachoTest.php`: Vendedor crea
  solicitud con líneas; clasificación `inventario` vs `compra_externa`; consecutivo `SD-#####`; estado
  `solicitada` (FR-019, FR-020)
- [x] T055 [P] [US6] Feature test `tests/Feature/Despacho/CompraExternaTrazaTest.php`: la línea
  `compra_externa` guarda proveedor/costo/motivo "No disponible en almacén" y NO crea ítem de catálogo, NI
  lote, NI `movimientos_inventario` (FR-021)
- [x] T056 [P] [US6] Feature test `tests/Feature/Despacho/FlujoEntregaFirmadaTest.php`: recibir → remisionar
  → firmar → entregar; por cada línea de inventario un `movimientos_inventario` `origen='despacho'` con
  descuento de `stock_actual` y costo FIFO; solicitud pasa a `entregada` (FR-022, FR-024, FR-016)
- [x] T057 [P] [US6] Feature test `tests/Feature/Despacho/StockInsuficienteEnEntregaTest.php`:
  `confirmarEntrega` bloquea la línea de inventario sin stock suficiente y no descuenta el resto (FR-009 /
  escenario 7)
- [x] T058 [P] [US6] Feature test `tests/Feature/Despacho/AnularSolicitudTest.php`: anular en `solicitada`,
  `recibida` y `remisionada` deja estado `anulada` sin tocar `stock_actual` (escenario 6); no se puede
  anular una `entregada`
- [x] T059 [P] [US6] Feature test `tests/Feature/Despacho/RemisionPdfTest.php`: `GET
  /despachos/{solicitud}/remision` devuelve PDF; `REM-#####` único; la firma va embebida; lista todas las
  líneas (inventario + compra externa) (FR-023, SC-007)
- [x] T060 [P] [US6] Policy test `tests/Feature/Despacho/DespachoPolicyTest.php`: Vendedor no puede
  recibir/remisionar/entregar; Almacenista no puede crear; ambos y el Administrador pueden anular (403 en
  los casos negados)

### Implementation for User Story 6

- [x] T061 [US6] Componente Volt `despacho.index` — `resources/views/livewire/despacho/index.blade.php`:
  bandeja; el Vendedor ve sólo sus solicitudes, el Almacenista/Administrador ven todas con filtro por
  estado; enlace a "Nueva solicitud" (Vendedor) y a la vista de entrega (Almacenista)
- [x] T062 [US6] Componente Volt `despacho.form` — `resources/views/livewire/despacho/form.blade.php`: alta/
  edición de solicitud (cliente + observaciones) con repetidor de líneas: selector de ítem de inventario
  (muestra stock) o casilla "No está en almacén" → campos `descripcion` + `proveedor_externo` +
  `costo_compra_externa`; validación de integridad de línea (T047); acción "Enviar al almacén"
  (`DespachoService::enviar`)
- [x] T063 [US6] Componente Volt `despacho.entrega` — `resources/views/livewire/despacho/entrega.blade.php`:
  acciones del Almacenista (Recibir → Generar remisión → Confirmar entrega); líneas separadas en "de
  inventario" y "compra externa"; captura de firma con `<canvas>` + JS inline que serializa a PNG base64 en
  un `<input type="hidden" wire:model>`; campos nombre y documento de quien recibe
- [x] T064 [US6] `app/Http/Controllers/RemisionEntregaController.php` + plantilla
  `resources/views/pdf/remision-entrega.blade.php` (dompdf): encabezado con `REM-#####`, cliente, líneas,
  y la firma embebida como `data:image/png;base64,...`
- [x] T065 [US6] Rutas en `routes/web.php` dentro del grupo `auth`, tras
  `middleware('role:Vendedor|Almacenista|Administrador')`: `Volt::route('/despachos', 'despacho.index')`,
  `Volt::route('/despachos/nueva', 'despacho.form')`, `Volt::route('/despachos/{solicitud}',
  'despacho.entrega')`, `Route::get('/despachos/{solicitud}/remision', RemisionEntregaController::class)`
- [x] T066 [US6] Enlace de navegación "Despachos" en `resources/views/components/layout.blade.php` (o el
  partial de menú), visible para Vendedor/Almacenista/Administrador

### Polish for User Story 6

- [x] T067 [P] [US6] Índices: `solicitudes_despacho(estado)`, `(vendedor_id)`, `(cliente_id)`;
  `remisiones_entrega.numero` unique, `remisiones_entrega.solicitud_id` unique
- [x] T068 [US6] `php artisan test --filter=Despacho` en verde y `php artisan test --filter=Inventario`
  sigue en verde
- [x] T069 [US6] Actualizar `README.md` (fila de `/speckit-tasks` del spec 003 → completado rev. 2026-09-01)

### Pulido US6 (2026-09-01, tras revisión visual del usuario)

- [x] T070 [US6] Todo monto del canal (costo de línea, costo de compra externa, total estimado, PDF) pasa
  por `App\Support\Moneda::cop()` — nunca `number_format` suelto (spec 009, FR-001). El formulario muestra
  «Total estimado» en vivo.
- [x] T071 [US6] Corregido el modo oscuro que "se ponía en blanco" al enviar la solicitud: el redirect de
  `despacho.form` pasó de `navigate: true` a `navigate: false` (convención del proyecto: `auth.login` y
  `auth.reset-password` ya lo hacían), para que el script inline de `<head>` que aplica `.dark` vuelva a
  correr. Inputs del canal con `dark:bg-slate-800 dark:text-slate-100`. `<x-select>` de líneas con
  `reset-key` por `uid` de línea (spec 009, FR-004).
- [x] T072 [US6] Notificaciones en pantalla: (a) toast (`Notifies`) en cada acción de
  `despacho.entrega` (recibir/remisionar/entregar/anular); (b) badge rojo con el número de solicitudes
  pendientes por gestionar en el ítem de menú "Despachos" para Almacenista/Administrador; (c) aviso en
  `/despachos` ("Tienes N solicitudes pendientes por gestionar").
- [x] T073 [US6] Trazabilidad mixta para quien despacha: `despacho.entrega` separa y rotula «Despachar de
  bodega» vs «Compra externa — no disponible en almacén», con chip "stock insuficiente" por línea y un
  resumen ("N líneas para despachar de bodega · M líneas de compra externa"); en `/despachos` cada fila
  con compra externa muestra un chip "N ext.".

### Remisión física + copia al correo del cliente (2026-09-01, FR-026/FR-027)

- [x] T074 [US6] Migración `add_entrega_fields_to_remisiones_entrega_table` — `entregado_por_nombre`,
  `nota_entrega`, `enviada_al_cliente_en`. `RemisionEntrega` fillable/casts actualizados.
- [x] T075 [US6] PDF `pdf/remision-entrega.blade.php` rehecho al formato físico "REMISIÓN {sede} Nº ####":
  encabezado SERVIREPARAR + sede (`config/despachos.php`, env `DESPACHO_SEDE`) + consecutivo + fecha;
  bloque de cliente (nombre, dirección, NIT, teléfono, correo desde spec 000); tabla única «Despachamos a
  ustedes los siguientes artículos» (Cant. / Referencia / Descripción, sin costos); nota de entrega;
  bloques «Recibe» (nombre + C.C. + firma) y «Entrega» (nombre). Sin etiqueta de compra externa ni
  proveedor externo (trazabilidad interna). Montos por `Moneda::cop()` donde apliquen.
- [x] T076 [US6] `DespachoService::confirmarEntrega()` recibe `entregadoPorNombre` y `notaEntrega`; tras
  confirmar (fuera de la transacción) envía `App\Mail\RemisionEntregada` (PDF adjunto) al `Cliente.correo`
  y marca `enviada_al_cliente_en`; devuelve bool. `despacho.entrega` añade los campos «Entrega» y
  «Descripción de la entrega» y avisa por toast si se envió o no la copia (cliente sin correo).
- [x] T077 [US6] Tests: envío de copia al correo (`Mail::fake` + `assertSent` con `hasTo`), cliente sin
  correo → no se envía y `enviada_al_cliente_en` null, y el PDF no contiene "No disponible en almacén" /
  proveedor externo / "Compra externa" (`RemisionPdfTest`, `FlujoEntregaFirmadaTest`).
- [x] T078 [US6, FR-028] Remisión por ciudad: `config/despachos.php` con lista de sedes (sigla IATA →
  ciudad) + `sede_por_defecto` (env `DESPACHO_SEDE`); migración `add_sede_to_solicitudes_despacho_table`;
  `<x-select>` "Ciudad (remisión)" en `despacho.form` (default configurable); `DespachoService::crear()`
  recibe `$sede` (valida contra la lista); columna "Ciudad" en `/despachos` y en la vista de despacho.
  PDF: texto "SERVIREPARAR / S.A.S — Taller de servicios" reemplazado por el **logo embebido**
  (`public/img/logo.png` como data URI) y título "REMISIÓN {sede} — {ciudad}". Tests: `sede` en la
  solicitud (`CrearSolicitudDespachoTest` fija `MDE`) y "REMISIÓN BAQ" en el PDF (`RemisionPdfTest`).
  **15 tests del canal en verde, 109 en total.**
- [x] T079 [US6, FR-024] **Doble firma en la remisión**: además de la firma de quien recibe (`firma`) se
  captura la firma de **quien entrega** (`firma_entrega`); ambas obligatorias para confirmar. Migración
  `add_firma_entrega_to_remisiones_entrega_table`; `DespachoService::confirmarEntrega()` +param
  `$firmaEntrega` (validado no vacío); PDF con bloque «Entrega» (firma + nombre) junto al de «Recibe».
  Tests: firma_entrega persistida y entrega bloqueada si falta (`FlujoEntregaFirmadaTest`).
- [x] T080 [US6] Ajustes de UI pedidos por el usuario: (a) logo del sidebar/topbar agrandado
  (`layout.blade.php`, `h-[22px]→h-9`, `h-4→h-6`); (b) firmas **secuenciales** en `despacho.entrega`, ya no
  lado a lado — primero se firma «quien recibe» y solo entonces (Alpine `x-show`, `firmaPad` con callback
  `onChange`) aparece el lienzo de «quien entrega». **16 tests del canal, 110 en total.**

**Checkpoint**: US6 funcional de forma independiente — Vendedor crea, Almacén entrega contra remisión
firmada, compra externa trazada sin tocar stock. Requiere la Fase 8 de spec 001 (rol Vendedor) completa.

---

## Dependencies & Execution Order

- **Setup + Foundational** bloquean todo — `MovimientoService` (atomicidad) es prerrequisito de cualquier
  historia que mueva stock.
- **US1** es el MVP (integración crítica con OT).
- **US2, US3, US4** son independientes entre sí una vez completada la Fase 2.
- **US5** (codificación/ubicación/código de barras) es independiente funcionalmente pero se recomienda
  antes de US1-US4 en la práctica, ya que sin código interno los ítems no tienen identificador limpio — el
  orden de las fases aquí sigue la prioridad del spec, no una dependencia técnica estricta.
- **US6** (venta mostrador sin OT, Fase 10) sólo depende de la Fase 2 (`MovimientoService`) y de la Fase 8
  de **spec 001** (rol `Vendedor` + permiso `manage-despachos`). Es independiente de US1-US5 y de spec 002.

## Implementation Strategy

MVP = US1 (atender solicitudes desde OT), por ser la integración más crítica con spec 002. US5
(codificación/barras) puede desarrollarse en paralelo desde el inicio ya que solo depende de la Fase 2.
