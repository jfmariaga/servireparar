# Plan de endurecimiento del flujo de OT — Phase 12 (2026-09-10)

**Contexto**: tras Phase 11 (mergeada a `main`, 198 tests) el cliente revisó el flujo completo y pidió
seis ajustes de negocio. Este documento los fija: qué cambia, contra qué código, en qué orden y cómo se
prueba. Es la continuación de `docs/PLAN-ENDURECIMIENTO-OT-2026-09-08.md` (Phase 11).

- Origen: conversación con el cliente 2026-09-10.
- Alcance: spec 002 (OT) + micro-ajustes de spec 003 (cola de préstamo de herramienta en Bodega).
- Backlog ejecutable: `specs/002-ordenes-trabajo/tasks.md` → **Phase 12** (T099–T137).

### Avance

| Fase | Estado |
|------|--------|
| 12.1 — Trazabilidad y checklist (T099–T102) | ✅ hecho (2026-09-10). `eventos()` en orden ascendente; checklist en solo lectura hasta que todas las tareas activas estén finalizadas (UI + guarda de servidor, helper `OrdenTrabajo::tareasActivasFinalizadas()`). 202 tests. |
| 12.2 — Prerrequisitos entre tareas (T103–T110) | ✅ hecho (2026-09-10). Pivote `detalle_ot_prerrequisitos` + `detalle_ot.orden`; `prerrequisitosPendientes()`; validación sin ciclos (DFS) en `crear`/`agregarTarea`/`actualizarTarea`; guarda en `iniciarTarea`; cancelar/quitar libera dependientes; UI de dependencias + reorden ↑/↓. 209 tests. |
| 12.3 — "Liberar OT" y notificaciones diferidas (T111–T117) | ✅ hecho (2026-09-10). `EstadoOtService::liberar()` (alias `planificar()`); estado `en_revision` se muestra "Planificación"; al crear no se avisa a técnicos/Bodega (solicitudes silenciosas, stock reservado); al liberar se avisa a técnicos y —si hay insumos pendientes— a Bodega. La OT no se congela. 216 tests. |
| 12.4 — Insumo al encargado de la tarea (T118–T122) | ⬜ pendiente |
| 12.5 — Herramientas: préstamo por técnico (T123–T134) | ⬜ pendiente |
| 12.6 — Regresión y cierre (T135–T137) | ⬜ pendiente |

---

## 1. Decisiones de negocio (confirmadas por el cliente, 2026-09-10)

Continúan la numeración de Phase 11 (D1–D8).

| # | Tema | Decisión |
|---|------|----------|
| D9 | Checklist de cierre | **Solo se habilita al final.** Se muestra siempre (solo lectura); las respuestas se habilitan únicamente cuando **todas las tareas activas están `finalizada`**. Guarda de servidor además de la UI. |
| D10 | Prerrequisitos entre tareas | Una tarea puede depender de **una o varias** tareas de la misma OT. **No se puede iniciar** una tarea con prerrequisitos sin `finalizar`. Se define al **crear / agregar / editar** tarea. UI: lista de tareas **ordenable (drag & drop)** + marca "depende de la(s) anterior(es)" y selector para dependencias no contiguas. Sin ciclos. Si se **cancela** una tarea, sus dependientes **se liberan de ese vínculo** (conservan los demás prerrequisitos). |
| D11 | Orden de la trazabilidad | La bitácora `ot_eventos` se muestra **cronológica ascendente** (creación de la OT primero, luego solicitudes de insumo, etc.). Hoy sale invertida y no tiene lógica. |
| D12 | "Liberar OT" | El estado inicial pasa a llamarse **"Planificación"** (slug `en_revision` sin cambios). El botón "Planificar OT" → **"Liberar OT"** (`EstadoOtService::liberar()`); transición `en_revision → pendiente` (el nombre `pendiente` **se mantiene**). **No se congela** la OT al liberar: el botón **"Corregir OT" sigue disponible** (FR-009 intacto). El **badge del menú** y el texto de la notificación de creación **no se renombran**. |
| D13 | Momento de las notificaciones | Al **crear** la OT ya **no** se notifica a técnicos ni a Bodega. Al pulsar **"Liberar OT"**: (a) se notifica a **cada técnico con tarea activa en la OT**; (b) se notifica a **Almacén solo si hay ≥1 `solicitud_insumo_ot` en estado `pendiente`**. Las solicitudes de insumo se siguen creando al guardar la tarea (reserva de stock), pero **silenciosas** hasta liberar. |
| D14 | Destinatario del insumo | El insumo se entrega **al encargado de la tarea** (`detalle_ot.tecnico_id`). El destinatario **no es modificable** por Bodega; la cola solo lo muestra. Queda registrado en la solicitud y en el movimiento de salida. |
| D15 | Herramientas fuera del ciclo de la OT | Se elimina "asignar herramienta desde la OT" (sección del detalle, `OtHerramientaService::asignar` desde ahí, selector). El **técnico** solicita las herramientas él mismo. El vínculo duro es con el **técnico** (para saber quién no ha devuelto); la tarea/OT es solo **contexto**. Las herramientas **dejan de bloquear** la solicitud de salida del equipo, la entrega y la cancelación de la OT. La **devolución la registra el almacenista** al recibirla físicamente. El **Jefe de Taller no interviene** en herramientas. |
| D16 | Reutilización de `ot_herramientas` | Se **reconvierte** `ot_herramientas` a préstamo por técnico (nuevas columnas + `ot_id` nullable) en vez de crear una tabla nueva. Modelo `PrestamoHerramienta`. Nuevo `origen = 'prestamo'` en `movimientos_inventario`. |

---

## 2. Hallazgos → tareas

Severidad: 🔴 crítico · 🟠 alto · 🟡 medio · 🔵 bajo. Todos son cambios de negocio pedidos, no defectos, salvo H26.

| ID | Sev | Punto | Tareas |
|----|-----|-------|--------|
| H24 | 🟡 | El checklist se puede responder desde el inicio; debe estar bloqueado hasta que todas las tareas estén finalizadas (D9). | T100, T101 |
| H25 | 🟠 | No hay dependencias entre tareas: cualquier tarea se inicia en cualquier orden (D10). | T103–T110 |
| H26 | 🔵 | `OrdenTrabajo::eventos()` usa `->latest()`; la bitácora se ve al revés (D11). | T099 |
| H27 | 🟠 | El paso previo a ejecución se llama "Planificar" y no comunica que "libera" la OT; las notificaciones de técnico/Bodega salen al **crear**, no al **liberar** (D12, D13). | T111–T117 |
| H28 | 🟡 | La entrega de insumo no registra a qué técnico va; Bodega no sabe a quién entregar (D14). | T118–T122 |
| H29 | 🟠 | Las herramientas se asignan desde la OT por el Jefe y bloquean el cierre; debe ser un préstamo que pide el técnico y no toca el ciclo de la OT (D15, D16). | T123–T134 |

---

## 3. Fases y backlog (T099–T137)

### Fase 12.1 — Trazabilidad y checklist (H24, H26)
- **T099** `OrdenTrabajo::eventos()` → `orderBy('created_at')->orderBy('id')` (ascendente). Ajustar el
  `@foreach ($ot->eventos …)` de `detalle.blade.php` (línea ~1046) y los tests que asuman orden inverso.
- **T100** `detalle.blade.php`: los ítems del checklist se renderizan en **solo lectura** mientras
  `! $tareasActivasTodasFinalizadas`; las respuestas (radio SI/NO/obs.) se habilitan solo cuando todas
  las tareas activas están `finalizada`. Mensaje: "El checklist se habilita cuando todas las tareas
  estén finalizadas". Se conserva el aviso "Faltan N respuestas…" una vez habilitado.
- **T101** Guarda de servidor en el método que responde el checklist (`responderChecklist` / servicio):
  `ValidationException` si queda alguna tarea activa sin `finalizada`.
- **T102** [P] Tests: bitácora ascendente (creación antes que `insumo_solicitado`); el checklist no
  admite respuesta antes de finalizar todas las tareas; sí después.

### Fase 12.2 — Prerrequisitos entre tareas (D10 · H25)
- **T103** Migración: `detalle_ot.orden` (int, se puebla por el orden de creación actual) + tabla
  pivote `detalle_ot_prerrequisitos` (`detalle_ot_id`, `prerrequisito_id`, unique `(detalle_ot_id,
  prerrequisito_id)`, ambas FK a `detalle_ot` con `cascadeOnDelete`).
- **T104** `DetalleOt::prerrequisitos()` / `dependientes()` (belongsToMany self). Helper
  `prerrequisitosPendientes(): Collection` (prerrequisitos con `estado_tarea ∉ {finalizada, cancelada}`).
- **T105** Validación en `OrdenTrabajoService::crear / agregarTarea / actualizarTarea`: los
  prerrequisitos son de la **misma OT**, una tarea no se depende de sí misma, y **no se admiten ciclos**
  (DFS sobre el grafo antes de guardar). `ValidationException` con mensaje claro.
- **T106** Guarda en `iniciarTarea()`: `ValidationException` "La tarea «X» requiere finalizar antes:
  «A», «B»." si `prerrequisitosPendientes()` no está vacía. Se mantiene el update condicional por
  `estado_tarea` de Phase 11 (H14).
- **T107** `cancelarTarea()` y `quitarTarea()`: al cancelar/eliminar una tarea, **desvincularla como
  prerrequisito** de todas sus dependientes (`detalle_ot_prerrequisitos` where `prerrequisito_id = X`).
  Registrar `correccion` en la OT por cada dependiente liberada.
- **T108** UI:
  - `crear.blade.php`: lista de tareas **ordenable** (drag & drop, persiste `orden`); por tarea, checkbox
    "Depende de la tarea anterior" (atajo: vincula con la inmediatamente anterior según `orden`) + un
    selector múltiple "Otras tareas de las que depende" para dependencias no contiguas.
  - `detalle.blade.php`: misma lista ordenable; en cada tarea, "Requiere: #n, #m" y badge **"Bloqueada
    (esperando prerrequisitos)"** cuando aplica.
- **T109** Tablero / detalle: el botón **"Iniciar"** de una tarea bloqueada por prerrequisitos aparece
  deshabilitado con tooltip.
- **T110** [P] Tests: no se inicia con prerrequisito pendiente; sí al finalizarlo; ciclo rechazado en
  creación y edición; multi-prerrequisito; cancelar un prerrequisito libera a la dependiente; el estado
  de la OT sigue derivándose bien (tareas bloqueadas cuentan como `pendiente`).

### Fase 12.3 — "Liberar OT" y notificaciones diferidas (D12, D13 · H27)
- **T111** `EstadoOtService::planificar()` → **`liberar()`** (mismo cuerpo; evento "OT liberada por el
  Jefe de Taller: en_revision → pendiente"). Mantener `planificar()` como alias `@deprecated` que llama
  a `liberar()` hasta ajustar todas las llamadas/tests.
- **T112** Renombrar el **nombre** (no el slug) del estado `en_revision` a **"Planificación"**:
  `EstadoOtSeeder` + migración de datos `update`.
- **T113** `detalle.blade.php`: botón "Planificar OT" → **"Liberar OT"** (label + `wire:click="liberar"`,
  método `liberar()`). **No** tocar `estaBloqueada()`: "Corregir OT" sigue visible tras liberar.
- **T114** Quitar del **momento de creación** las notificaciones a técnicos y a Almacén
  (`OrdenTrabajoService::crear`, listener `NotificarEventosOt`, evento `OtCreada`). `OtCreadaNotification`
  → decidir en implementación si se mantiene solo para Jefes o se elimina por redundante.
- **T115** En `liberar()`:
  - `OtLiberadaNotification` → cada `tecnico->usuario` con **tarea activa** en la OT ("OT OTSV-… liberada;
    puedes ejecutar tus tareas");
  - `SolicitudInsumoPendienteNotification` → Almacenistas **solo si**
    `ot->solicitudesInsumo()->where('estado','pendiente')->exists()`.
- **T116** `SolicitudInsumoService::sincronizarDesdeTarea()`: sigue creando/actualizando solicitudes y
  reservando stock, pero **sin notificar a Bodega**. Excepción: si la tarea se edita **después** de
  liberar y aparece una línea de insumo nueva `pendiente`, notificar a Bodega en ese momento.
- **T117** [P] Tests: crear OT no notifica a técnico ni Bodega; `liberar()` notifica a los técnicos de
  la OT; `liberar()` con insumos `pendiente` notifica a Bodega; sin insumos, no; agregar insumo a una
  tarea tras liberar notifica a Bodega; el badge del menú y `OtCreadaNotification` intactos.

### Fase 12.4 — Insumo al encargado de la tarea (D14 · H28)
- **T118** Migración: `solicitudes_insumo_ot.entregado_a_tecnico_id` (nullable, FK `tecnicos`). Backfill
  desde `detalle_ot_insumo → detalle_ot.tecnico_id`.
- **T119** `SolicitudInsumoService`: al crear la línea, `entregado_a_tecnico_id = detalleOt->tecnico_id`;
  recalcular si la tarea cambia de técnico **mientras la solicitud siga `pendiente`**.
- **T120** `AtencionInsumoOtService::entregar()`: pasar el técnico destinatario a
  `MovimientoService::salida` (motivo/referencia "OT … — insumo para <técnico>"). **Sin override.**
- **T121** `inventario/solicitudes-ot.blade.php`: columna **"Entregar a"** (solo lectura) con el nombre
  del técnico de la tarea.
- **T122** [P] Tests: la solicitud lleva el técnico de su tarea; cambia al reasignar la tarea antes de
  entregar; no cambia tras `entregada`; Bodega no puede alterarlo.

### Fase 12.5 — Herramientas: préstamo por técnico (D15, D16 · H29)
- **T123** Migración: reconvertir `ot_herramientas` →
  `tecnico_id` (NOT NULL, FK `tecnicos`), `estado` enum `('solicitada','entregada','rechazada','devuelta')`,
  `solicitada_en`, `entregada_por` (FK users, nullable), `recibida_por` (FK users, nullable),
  `motivo_rechazo` (nullable), `detalle_ot_id` (nullable, FK, contexto); `ot_id` → **nullable**.
  `asignada_por` / `asignada_en` → se dejan de escribir (o se retiran).
  Enum `movimientos_inventario.origen` += `'prestamo'`. Migración de datos de filas existentes
  (`tecnico_id` desde la tarea de la OT / desde `asignada_por` como fallback).
- **T124** Modelo **`PrestamoHerramienta`** (tabla `ot_herramientas` reutilizada). Scopes
  `pendientesDe(Tecnico)`, `sinDevolver()`, `enColaDeBodega()`.
- **T125** `OtHerramientaService` → **`PrestamoHerramientaService`**:
  - `solicitar(Tecnico $t, Inventario $h, ?DetalleOt $tarea)` — valida `tipo=herramienta`; estado
    `solicitada`; evento.
  - `entregar(PrestamoHerramienta $p, User $almacenista)` — valida `estado_herramienta='disponible'`;
    `MovimientoService::salida(origen:'prestamo')`; marca `en_uso`; `estado='entregada'`.
  - `rechazar($p, $almacenista, string $motivo)` — `estado='rechazada'` + motivo.
  - `registrarDevolucion($p, $almacenista, string $estadoDevolucion)` —
    `MovimientoService::devolucion(origen:'prestamo')`; `estado='devuelta'`; `recibida_por`.
- **T126** Quitar del detalle de la OT: sección "Herramientas asignadas", `asignarHerramienta()` /
  `devolverHerramienta()`, `herramientasDisponibles`, props relacionadas.
- **T127** **Quitar guardas** de herramienta del ciclo de la OT:
  - `SalidaEquipoService::solicitarSalida()` (la validación "Hay herramientas de esta OT sin devolver…");
  - cualquier check equivalente en `confirmarEntrega()`;
  - `OrdenTrabajoService::cancelarOt()` ("Devuelve las herramientas asignadas antes de cancelar…").
- **T128** Vista del **Técnico** (en el detalle de una OT/tarea suya): "Solicitar herramienta" (selector
  `tipo=herramienta` disponibles). Lista **"Mis herramientas en préstamo"** con estado y fecha.
- **T129** Vista de **Bodega**: cola `/inventario/herramientas` (o pestaña en la pantalla de solicitudes):
  préstamos `solicitada` → **Entregar** / **Rechazar**; préstamos `entregada` → **Registrar devolución**
  (con estado `disponible` / `dañada` / `en_mantenimiento`). Permiso: `attend-ot-insumo` o nuevo
  `attend-prestamo` (Almacenista + Admin).
- **T130** Panel **"Herramientas por técnico"** (Bodega / Jefe / Admin, solo lectura): quién tiene qué
  sin devolver y desde cuándo.
- **T131** `CosteoOtService`: verificar que no quede ninguna referencia a `ot->herramientas` (las
  herramientas nunca sumaron al costeo; el préstamo tampoco).
- **T132** Notificaciones: `PrestamoSolicitado` → Almacenistas; `PrestamoEntregado` /
  `PrestamoRechazado` → técnico solicitante.
- **T133** `nav-items.blade.php`: badge **"Préstamos por atender (N)"** para Almacenista. **No** tocar
  los badges existentes ("Insumos para OT", "Salidas por aprobar", "OT por planificar").
- **T134** [P] Tests: el técnico solicita; el almacenista entrega (herramienta `en_uso`, movimiento
  `origen='prestamo'`); rechazo con motivo; la devolución la registra el **almacenista** y fija estado;
  la OT **finaliza / entrega / cancela con** préstamos sin devolver (ya no bloquean); el Jefe de Taller
  no ve acciones de herramienta en la OT.

### Fase 12.6 — Regresión y cierre
- **T135** `php artisan test` completo en verde; actualizar el contador en README.
- **T136** `docs/BITACORA-2026-09-10.md` con lo ejecutado; marcar T099–T137 en `tasks.md`.
- **T137** Ejecutar el **Protocolo de pruebas de aceptación** (§4) con los roles y registrar resultados.

---

## 4. Protocolo de pruebas de aceptación (Phase 12)

**Preparación:** `php artisan migrate:fresh --seed`; sesiones separadas por rol (password `password`):
`admin@`, `jefe@`, `almacen@`, `tecnico1@`, `tecnico2@`, `tecnico3@servireparar.com`.

Marca cada paso **OK** / **FALLA** (con nota).

### Bloque L — Trazabilidad y checklist
| # | Rol | Acción | Esperado |
|---|-----|--------|----------|
| L1 | Jefe | Crear una OT con 2 tareas e insumos; abrir la **bitácora**. | Orden **ascendente**: "creación" arriba, luego "insumo solicitado", etc. |
| L2 | Jefe | Abrir el **checklist** con tareas sin finalizar. | Ítems visibles pero **no editables**; mensaje "se habilita cuando todas las tareas estén finalizadas". |
| L3 | Jefe | Intentar responder el checklist vía acción directa (doble submit / consola). | Rechazado por el servidor. |
| L4 | Jefe | Finalizar todas las tareas; volver al checklist. | Respuestas **habilitadas**; aviso "Faltan N respuestas…". |

### Bloque M — Prerrequisitos entre tareas
| # | Rol | Acción | Esperado |
|---|-----|--------|----------|
| M1 | Jefe | OT con Tarea A (Carlos), Tarea B (Diana, depende de A) y Tarea C (Andrés, depende de A y B). Reordenar por drag & drop. | Orden persiste; B y C muestran "Requiere: …". |
| M2 | Jefe | Intentar marcar C como prerrequisito de A (ciclo). | Rechazado: "dependencia circular". |
| M3 | Diana | Intentar iniciar B con A sin finalizar. | Bloqueado: "requiere finalizar «A»". Botón "Iniciar" deshabilitado. |
| M4 | Carlos → Jefe | Finalizar A. | B se desbloquea; C sigue bloqueada (falta B). |
| M5 | Jefe | Cancelar B (motivo). | C **se libera** de B (sigue requiriendo A, ya finalizada) → C iniciable; evento `correccion` en la OT. |

### Bloque N — Liberar OT y notificaciones
| # | Rol | Acción | Esperado |
|---|-----|--------|----------|
| N1 | Jefe | Crear una OT con insumos. | Estado **"Planificación"**. Técnicos y Almacén **no** reciben nada. Solicitudes de insumo creadas en `pendiente` (stock reservado). |
| N2 | Jefe | Botón **"Liberar OT"**. | Estado → **Pendiente**; evento "OT liberada". Cada técnico de la OT recibe notificación; Almacén recibe "insumos pendientes" (hay líneas `pendiente`). |
| N3 | Jefe | Crear otra OT **sin insumos** y liberarla. | Técnicos notificados; Almacén **no** (no hay solicitudes). |
| N4 | Jefe | Tras liberar, usar **"Corregir OT"** y agregar una tarea con un insumo nuevo. | Permitido (no se congela). Almacén recibe notificación de la nueva línea. |

### Bloque O — Insumo al encargado de la tarea
| # | Rol | Acción | Esperado |
|---|-----|--------|----------|
| O1 | Almacenista | Abrir `/inventario/insumos-ot`. | Cada línea muestra **"Entregar a: <técnico de la tarea>"**, sin poder cambiarlo. |
| O2 | Jefe | Antes de entregar, reasignar la tarea a otro técnico. | El destinatario de la solicitud `pendiente` se actualiza. |
| O3 | Almacenista | Entregar la línea. | Movimiento de salida con el técnico como destinatario; ese técnico recibe "insumo disponible". |
| O4 | Jefe | Reasignar la tarea después de entregada. | El destinatario **ya no** cambia. |

### Bloque P — Préstamo de herramienta por técnico
| # | Rol | Acción | Esperado |
|---|-----|--------|----------|
| P1 | Jefe | Abrir el detalle de una OT. | **No** existe la sección "Herramientas asignadas" ni acción de herramienta. |
| P2 | Carlos | En su tarea, **"Solicitar herramienta"** → Torquímetro. | Préstamo `solicitada`; Almacén recibe notificación. |
| P3 | Almacenista | Cola de herramientas → **Entregar** a Carlos. | Torquímetro `en_uso`; movimiento `origen='prestamo'`; Carlos notificado. |
| P4 | Diana | Solicitar una herramienta no disponible. | Almacenista puede **Rechazar** con motivo; Diana notificada. |
| P5 | Jefe | Llevar la OT de Carlos hasta **Finalizada → salida → Entregada** **sin** que Carlos devuelva el Torquímetro. | El flujo **no se bloquea** por el préstamo pendiente. |
| P6 | Bodega | Panel **"Herramientas por técnico"**. | Carlos aparece con el Torquímetro sin devolver, con fecha. |
| P7 | Almacenista | Carlos entrega físicamente; **Registrar devolución** (estado "disponible"). | Préstamo `devuelta`; movimiento `devolucion`; herramienta `disponible`; `recibida_por` = almacenista. |
| P8 | Jefe | Buscar cualquier acción de herramienta. | No hay: el Jefe no interviene. |

---

## 5. Riesgos y notas

- **Migración de `ot_herramientas` (T123)**: si hay datos de Phase 11 en `main`, la reconversión debe
  derivar `tecnico_id`. En entornos limpios (`migrate:fresh`) es directo.
- **`planificar()` → `liberar()`**: hay tests de Phase 11 que llaman `planificar()`. Mantener el alias
  `@deprecated` hasta migrarlos en la misma fase (T111/T117).
- **Notificaciones movidas (D13)**: coordinar con el micro-slice de spec 008 de Phase 11 — mismo canal
  `database`, mismas clases `Notification`. Anotar en spec 008.
- **`navigate: false`** en todos los redirects de Livewire (convención del repo).
- **Dinero** siempre por `App\Support\Moneda::cop()`.
- **`estado_id` de la OT** lo sigue escribiendo solo `EstadoOtService`.
- El **badge del menú** y el texto "Nueva OT por planificar" **se dejan como están** (decisión del
  cliente), aunque el estado se llame ahora "Planificación" y el botón "Liberar OT".
