# Plan de endurecimiento del flujo de OT — 2026-09-08

**Contexto**: revisión minuciosa del flujo completo de Órdenes de Trabajo (spec 002) tras el
`/speckit-implement` del 2026-09-07. El módulo funciona como demo pero **no está sincronizado en
sentido industrial**: el insumo no se valida ni se reserva hasta el último momento, no hay
notificaciones entre roles, y varios pasos del flujo se pueden saltar. Este documento es la
trazabilidad de qué se va a corregir, en qué orden, y cómo se prueba.

- Auditoría de origen: sesión 2026-09-08 (hallazgos H1–H23, ver §2).
- Alcance: refuerzo del spec 002 + micro-slice del spec 008 (campana in-app) necesario para cerrar el
  flujo. No sustituye al spec 008 completo.
- Backlog ejecutable: `specs/002-ordenes-trabajo/tasks.md` → **Phase 11** (T050–T098).

### Avance

| Fase | Estado |
|------|--------|
| 0 — Base y respaldo | ✅ hecho (2026-09-08). Rama `feature/ot-endurecimiento`; baseline 159 tests en verde. |
| 1 — Multi-insumo por tarea (T051–T058) | ✅ hecho (2026-09-08). Tabla `detalle_ot_insumos`; 1 solicitud por línea; estado `cancelada` con traza; costeo excluye rechazada/cancelada. + bloqueo de quitar/re-cantidad de línea ya entregada. |
| 2 — Reserva y bloqueo de stock (T059–T064) | ✅ hecho (2026-09-08). `Inventario::disponible()` = stock − comprometido; bloqueo duro con `lockForUpdate` al guardar la tarea; selectores muestran "disp. N"; panel "Insumos de la OT" en el detalle; costeo separa `repuestos_estimados`. 170 tests en verde. |
| 3 — Bodega de un solo paso (T065–T068) | ✅ hecho (2026-09-08). Se elimina el estado `aprobada` de las solicitudes de insumo: `pendiente → entregada \| rechazada \| cancelada`. Pantalla de Bodega sin "Aprobar"/"Aprobadas"; muestra disponible vs solicitado. 173 tests. |
| 4 — Herramientas en la OT (T069–T074) | ✅ hecho (2026-09-08). Tabla `ot_herramientas`; `OtHerramientaService::asignar/devolver` (movimientos `salida`/`devolucion` origen `ot`); sección "Herramientas asignadas" en el detalle; la salida del equipo se bloquea si quedan herramientas sin devolver; el costeo no cuenta herramientas. 178 tests. |
| 5 — Máquina de estados y guardias (T075–T082) | ✅ hecho (2026-09-08). Botón "Planificar OT" y `executeTareas` exige OT planificada (H6); finalizar tarea con insumo sin entregar → confirmación del Jefe (D3); reapertura de OT finalizada invalida la salida aprobada (H7); updates condicionales en iniciar/finalizar (H14); estado `cancelada` para OT (terminal) y tarea, con liberación de reservas y guardia de herramientas (D8). 186 tests. |
| 6 — Notificaciones in-app + alertas (T083–T091) | ✅ hecho (2026-09-08). Tabla `notifications`; `OtNotificacion` + `NotificadorOt` (avisos por rol); listener `NotificarEventosOt` para OtCreada/OtEntregada/OtProximaAVencer/StockBajo + disparo directo desde los servicios de Bodega/salida; correo `OtEntregadaCliente` (FR-011); campana Volt con `wire:poll` en el layout; badges en el menú (OT por planificar / insumos pendientes / salidas por aprobar); anti-spam de vencimientos (`alertado_vencimiento_en` + `--reenviar`). 193 tests. |
| 7 — Roles, visibilidad, checklist (T092–T095) | ✅ hecho (2026-09-08). `OrdenTrabajo::scopeVisiblesPara()` + `OrdenTrabajoPolicy::view` — el Técnico solo ve las OT donde tiene tareas (D7); permiso `attend-ot-insumo` (Almacenista + Admin) en la pantalla de Bodega (H18); checklist de cierre por defecto (`config/ot.php` `checklist_por_defecto`) + aviso "Faltan N respuestas…" (H16). 197 tests. |
| 8 — Regresión y cierre (T096–T098) | ✅ hecho (2026-09-08). Suite: **198 tests en verde**. `docs/BITACORA-2026-09-08.md`. Rama **mergeada a `main`** (ff). **T098** ejecutado: flujo A–K verificado end-to-end por script + paseo visual con los 5 roles (18 capturas); 1 ajuste (insumo rechazado retiene la finalización). Falta `git push`. |
| extra — congelar OT tras aprobar la salida + `valor_proyecto` obligatorio antes de solicitarla + confirmaciones SweetAlert2 + datos demo de inventario | ✅ hecho (2026-09-08), fixes surgidos en pruebas. |

---

## 1. Decisiones de negocio (confirmadas por el cliente, 2026-09-08)

| # | Tema | Decisión |
|---|------|----------|
| D1 | Sobre-compromiso de stock | **Bloqueo duro al guardar la tarea** si `cantidad > disponible` (consumibles), con advertencia visible del disponible real. Sin "faltante a Compras" por ahora. |
| D2 | Paso "Aprobar" de Bodega | **Se elimina.** La solicitud va directo `pendiente → entregada` (o `rechazada`). Un solo control: la entrega. |
| D3 | Finalizar tarea con insumo no entregado | **Requiere confirmación del Jefe de Taller.** El técnico marca "lista para finalizar"; si algún insumo de la tarea no está entregado, la finalización real la confirma el Jefe. Si todo está entregado, el técnico finaliza directo. |
| D4 | Herramientas en la OT | **Se incluyen** con flujo de **asignación + devolución**. Devolución obligatoria antes de entregar el equipo. |
| D5 | Campana de notificaciones | **In-app con polling 30–60 s** (default 45). Sin websockets por ahora. |
| D6 | Multi-insumo por tarea | **Sí, ahora.** Una tarea puede tener varias líneas de insumo (`detalle_ot_insumos`). |
| D7 | Visibilidad de OT para técnicos | **Solo sus OT** (donde tienen ≥1 tarea asignada). Admin/Jefe ven todas. |
| D8 | Cancelar OT / cancelar tarea | **En alcance.** Estado `cancelada` para OT (terminal) y para tarea; cancelar libera reservas de insumo. |

---

## 2. Hallazgos → tareas

Severidad: 🔴 crítico · 🟠 alto · 🟡 medio · 🔵 bajo.

| ID | Sev | Hallazgo | Tareas |
|----|-----|----------|--------|
| H1 | 🔴 | No existe stock comprometido/reservado; el inventario solo se valida al entregar. | T059, T060, T064 |
| H2 | 🔴 | Cero validación y cero aviso al agregar el insumo a la tarea; el selector no muestra stock. | T060, T061 |
| H3 | 🔴 | Los eventos (`OtCreada`, `OtEntregada`, `OtProximaAVencer`, `StockBajo`) no tienen listener. No se envía nada. FR-011 sin implementar. | T083–T088, T091 |
| H4 | 🔴 | Se puede finalizar una tarea con el insumo sin entregar o rechazado. | T077, T082 |
| H5 | 🔴 | El costeo suma repuestos rechazados / no entregados (cae al costo de referencia). | T063, T064 |
| H6 | 🔴 | El paso "planificar" (En revisión → Pendiente) es inalcanzable e ignorable. | T075, T076, T082 |
| H7 | 🔴 | `confirmarEntrega()` no mira el estado de la OT; una OT reabierta con salida aprobada se puede entregar. | T078, T079, T082 |
| H8 | 🟠 | Una tarea = un solo insumo (`detalle_ot.insumo_id`). | T051–T058 |
| H9 | 🟠 | Se puede elegir una herramienta como "insumo"; queda `en_uso` para siempre; entra al costeo. | T069–T074 |
| H10 | 🟠 | `entregar()` puede saltarse `aprobar()` (el paso es decorativo). | T065, T066, T068 |
| H11 | 🟠 | `sincronizarDesdeTarea()` borra/reescribe solicitudes en silencio, sin traza. | T055, T067, T068 |
| H12 | 🟠 | El comando de vencimientos depende de un scheduler que en local nadie corre; cuenta desde `created_at`. | T089, T090 |
| H13 | 🟠 | No hay indicador de carga del técnico al asignar (spec 004 US2). | T061 (nota), diferible a spec 004 T012–T013 |
| H14 | 🟡 | Sin transacción/lock en `iniciarTarea` / `finalizarTarea` / `responderChecklist`. | T080 |
| H15 | 🟡 | No hay estado "cancelada" para tarea ni OT. | T081, T082 |
| H16 | 🟡 | Sin plantilla de checklist por defecto; la OT se atasca sin explicación clara. | T094, T095 |
| H17 | 🟡 | Un técnico ve todas las OT y todo su detalle. | T092, T095 |
| H18 | 🟡 | El Almacenista atiende solicitudes con permiso genérico de inventario. | T093, T095 |
| H19 | 🟡 | No hay vista consolidada de "insumos de la OT" ni enlace a la cola de Bodega. | T062 |
| H20 | 🔵 | `MovimientoService::salida()` compara `decimal` como string. | T060 (al pasar por ahí) |
| H21 | 🔵 | `sincronizarDesdeTarea()` usa `auth()->id()` para `solicitada_por`. | T055 |
| H22 | 🔵 | Borrado en cascada puede llevarse una solicitud `entregada` y su rastro. | T081 (guardas) |
| H23 | 🔵 | `VencimientoOtService` re-dispara la alerta en cada corrida. | T089 |

---

## 3. Fases y backlog (T050–T098)

Orden pensado para minimizar retrabajo: primero el cambio de modelo (multi-insumo), luego reserva de
stock sobre ese modelo, después Bodega, herramientas, máquina de estados, notificaciones y por último
roles/UX y regresión.

### Fase 0 — Base y respaldo
- **T050** Commit del spec 002 actual (hoy solo en working tree) como respaldo del Hito 2. Correr
  `php artisan test` y registrar el baseline en verde.

### Fase 1 — Multi-insumo por tarea (D6 · H8)
- **T051** Migración `create_detalle_ot_insumos_table` (`detalle_ot_id`, `inventario_id`, `cantidad`,
  timestamps; unique `(detalle_ot_id, inventario_id)`).
- **T052** Migración de datos: `detalle_ot.insumo_id` + `cantidad_insumo` → filas en `detalle_ot_insumos`.
- **T053** Modelo `DetalleOtInsumo`; `DetalleOt::insumos()` (hasMany); reemplazar `requiereInsumo()` por
  `tieneInsumos()` / `lineasInsumo()`.
- **T054** `solicitudes_insumo_ot`: columna `detalle_ot_insumo_id`; una solicitud por línea de insumo.
  Migración de datos de las solicitudes existentes.
- **T055** `SolicitudInsumoService::sincronizarDesdeTarea()` itera todas las líneas: crea/actualiza/
  cancela solicitudes por línea, con evento en `ot_eventos` por cada alta, cambio y baja (resuelve H11/H21;
  `solicitada_por` desde el actor recibido, no `auth()`).
- **T056** UI del formulario de tarea (`crear.blade.php` + `detalle.blade.php`): repetidor de insumos
  (filas insumo + cantidad, agregar/quitar).
- **T057** `CosteoOtService`: soportar N líneas de insumo por tarea.
- **T058** Ajustar tests y factories existentes al nuevo modelo; `DetalleOtInsumoFactory`.

### Fase 2 — Reserva y bloqueo de stock (D1 · H1, H2, H5)
- **T059** `Inventario::comprometido()` y `Inventario::disponible()` =
  `stock_actual − Σ cantidad de solicitudes_insumo_ot.estado = 'pendiente'` para consumibles.
  Scope `SolicitudInsumoOt::pendientesDe($inventarioId)`.
- **T060** Validación en `OrdenTrabajoService` (`crear` / `agregarTarea` / `actualizarTarea`): por cada
  línea de insumo consumible, si `cantidad > Inventario::disponible()` → `ValidationException`
  (**bloqueo duro**) con mensaje "Solo N disponibles de «X» (M comprometidas en otras OT)". Castear
  `stock_actual` a `float` de paso (H20).
- **T061** `<x-select>` de insumos: cada opción muestra `nombre — disp. N udm`; opciones con
  `disponible ≤ 0` deshabilitadas; badge de disponible junto al campo cantidad. (Placeholder para la
  carga del técnico, H13, cuando entre spec 004 T012–T013.)
- **T062** Panel "Insumos de la OT" consolidado en `detalle.blade.php`: todas las líneas, estado de cada
  solicitud, disponible actual, enlace a `/inventario/insumos-ot?ot=OTSV-…` (H19).
- **T063** `CosteoOtService` repuestos = solicitudes `entregada` (costo real del movimiento) + líneas no
  entregadas marcadas "estimado"; **excluir `rechazada` y `cancelada`** (H5).
- **T064** Tests: la reserva descuenta disponible; una 2.ª OT no puede comprometer sobre el disponible;
  el costeo ignora rechazadas.

### Fase 3 — Bodega de un solo paso + trazabilidad (D2 · H10, H11)
- **T065** `AtencionInsumoOtService`: eliminar `aprobar()`; `entregar()` y `rechazar()` solo admiten
  `pendiente`. Enum `solicitudes_insumo_ot.estado` → `('pendiente','entregada','rechazada')`
  (migración + datos: `aprobada` → `pendiente`).
- **T066** `inventario/solicitudes-ot.blade.php`: quitar "Aprobar" y la pestaña "Aprobadas"; dejar
  Entregar / Rechazar; mostrar disponible vs solicitado con alerta roja cuando falte.
- **T067** Eventos `insumo_cancelado` / `insumo_modificado` en `ot_eventos` cuando la edición de una
  tarea cancela o cambia una solicitud (nunca en silencio).
- **T068** Tests: no existe el paso aprobar; entregar desde `pendiente`; el rechazo deja evento; editar
  una tarea registra el cambio de su solicitud.

### Fase 4 — Herramientas en la OT: asignación + devolución (D4 · H9)
- **T069** Migración `create_ot_herramientas_table` (`ot_id`, `inventario_id`, `asignada_por`,
  `asignada_en`, `devuelta_en`, `estado_devolucion` [`disponible`/`dañada`/`en_mantenimiento`],
  `movimiento_salida_id`, `movimiento_devolucion_id`).
- **T070** `OtHerramientaService`: `asignar()` (valida `estado_herramienta = disponible`; marca
  `en_uso` vía `MovimientoService::salida` origen `ot`) y `devolver()` (vía
  `MovimientoService::devolucion` con el nuevo estado).
- **T071** UI en `detalle.blade.php`: sección "Herramientas asignadas" (asignar desde selector filtrado
  a `tipo = herramienta` y disponibles; devolver eligiendo estado). El selector de **insumos** pasa a
  `Inventario::activos()->where('tipo','consumible')`.
- **T072** Guardia: no se puede **confirmar la entrega del equipo** si hay herramientas asignadas sin
  devolver (bloqueo con mensaje).
- **T073** `CosteoOtService`: las herramientas asignadas NO suman a "repuestos" (uso, no consumo).
- **T074** Tests: asignar/devolver; entrega bloqueada con herramienta pendiente; herramienta fuera del
  costeo de repuestos.

### Fase 5 — Máquina de estados y guardias (H4/D3, H6, H7, H14, H15/D8)
- **T075** Botón "Planificar OT" (Jefe de Taller) en `detalle.blade.php` → `EstadoOtService::planificar()`
  (`en_revision → pendiente`), con evento.
- **T076** `OrdenTrabajoPolicy::executeTareas` + `iniciarTarea`: exigir `estado ∈ {pendiente, en_curso}`;
  mensaje claro si la OT no está planificada.
- **T077** Confirmación del Jefe para finalizar tarea con insumo no entregado (D3): el técnico marca
  `lista_para_finalizar`; si alguna línea de insumo de la tarea no está `entregada`, la finalización
  real la ejecuta el Jefe (`OrdenTrabajoService::confirmarFinalizacionTarea`, permiso `manage-ot`), con
  evento. Si todo está entregado, el técnico finaliza directo.
- **T078** `SalidaEquipoService::confirmarEntrega()`: exigir `ot.estado === finalizada` además de
  `salida_estado === aprobada`.
- **T079** `EstadoOtService::reabrir()` y la reapertura por `agregarTarea` sobre OT finalizada:
  resetear `salida_estado = 'no_solicitada'`, limpiar `salida_*`, evento "salida invalidada por
  reapertura".
- **T080** Updates condicionales (`->where('estado_tarea', <esperado>)`) con verificación de filas
  afectadas en `iniciarTarea` / `finalizarTarea` / `responderChecklist` (H14).
- **T081** Estado `cancelada`: en `estados_ot` (terminal) y en `detalle_ot.estado_tarea`.
  `OrdenTrabajoService::cancelarTarea(motivo)` (tareas no finalizadas) y `cancelarOt(motivo)`
  (Admin/Jefe): libera reservas (solicitudes `pendiente` → `cancelada`), exige devolución de
  herramientas asignadas, deja evento. Botones "Cancelar tarea" / "Cancelar OT" en la UI. Guardas de
  borrado en cascada (H22).
- **T082** Tests: planificación obligatoria; entrega exige OT finalizada; la reapertura invalida la
  salida; cancelar OT libera reservas; cancelar tarea.

### Fase 6 — Notificaciones in-app + alertas (D5 · H3, H12, H23)
- **T083** Migración `notifications` (Laravel) + `Notifiable` en `User`. `config/ot.php`:
  `notif_poll_segundos` (default 45).
- **T084** Notifications: `OtCreadaNotification` (→ Jefes), `SolicitudInsumoCreada` (→ Almacenistas),
  `SolicitudInsumoRechazada` (→ Jefe + creador), `SolicitudInsumoEntregada` (→ técnico de la tarea),
  `SalidaSolicitada` (→ Admins), `SalidaRechazada` (→ Jefe + creador), `OtEntregadaNotification`
  (→ creador), `OtProximaAVencerNotification` (→ Jefe + Admin), `StockBajoNotification` (→ Almacenistas).
- **T085** Listeners que enganchan los eventos existentes (`OtCreada`, `OtEntregada`, `OtProximaAVencer`,
  `StockBajo`) + disparo directo desde `AtencionInsumoOtService` / `SalidaEquipoService` /
  `SolicitudInsumoService` donde no hay evento.
- **T086** `OtEntregada` → correo al cliente (FR-011): Mailable + plantilla, remitente configurable.
- **T087** Campana en el layout: componente Volt `notificaciones-campana` — badge de no leídas,
  dropdown últimas 15, marcar leídas, `wire:poll.{{ config }}s`.
- **T088** Badges en `nav-items.blade.php`: "Insumos para OT (N)" (Almacenista), "Salidas por aprobar
  (N)" (Admin), "OT por planificar (N)" (Jefe).
- **T089** Anti-spam de vencimientos (H23): `ordenes_trabajo.alertado_vencimiento_en`;
  `VencimientoOtService` dispara una sola vez por cruce de umbral.
- **T090** README + bitácora: sección "Scheduler" — `php artisan schedule:work` (dev) y cron
  `* * * * * php artisan schedule:run` (prod).
- **T091** Tests: cada notificación al rol correcto; la campana lista no leídas; el vencimiento no
  re-notifica.

### Fase 7 — Roles, visibilidad, checklist (D7 · H16, H17, H18)
- **T092** `OrdenTrabajoPolicy::view` + scope `OrdenTrabajo::visiblesPara(User)`: el Técnico solo ve OT
  con ≥1 tarea suya; Admin/Jefe ven todas. Aplicar en `tablero.blade.php` y `detalle` (403 si no).
- **T093** Permiso `attend-ot-insumo` (Almacenista, Admin) en `RolesSeeder`; usarlo en
  `inventario/solicitudes-ot.blade.php` en vez de `viewAny Inventario` (H18).
- **T094** Plantilla de checklist genérica por defecto al crear la OT (`config/ot.php`
  `checklist_por_defecto`) + mensaje "Faltan N respuestas del checklist para poder finalizar" en
  `detalle.blade.php` (H16).
- **T095** Tests: técnico no ve OT ajena (403); almacenista sin permiso no atiende; checklist por
  defecto presente.

### Fase 8 — Regresión y cierre
- **T096** `php artisan test` completo en verde; actualizar el contador en README y en la bitácora.
- **T097** `docs/BITACORA-2026-09-08.md` con lo ejecutado; marcar T050–T098 en
  `specs/002-ordenes-trabajo/tasks.md`.
- **T098** Ejecutar el **Protocolo de pruebas de aceptación** (§4) con los 5 roles y registrar
  resultados.

---

## 4. Protocolo de pruebas de aceptación (prueba conjunta)

**Preparación (una sola vez):**

- `php artisan migrate:fresh --seed`
- 5 sesiones separadas (ventanas de incógnito), una por rol. Password de todos: `password`.

| Rol | Usuario |
|-----|---------|
| Administrador | `admin@servireparar.com` |
| Jefe de Taller | `jefe@servireparar.com` |
| Almacenista | `almacen@servireparar.com` |
| Vendedor | `vendedor@servireparar.com` |
| Técnico (Carlos Soldador) | `tecnico1@servireparar.com` |
| Técnico (Diana Mecánica) | `tecnico2@servireparar.com` |
| Técnico (Andrés Eléctrico) | `tecnico3@servireparar.com` |

Terminal a mano para `php artisan ot:revisar-vencimientos`.

Marca cada paso: **OK** / **FALLA** (con nota). "Esperado" describe el comportamiento **tras** el
endurecimiento.

### Bloque A — Datos base
| # | Rol | Acción | Esperado |
|---|-----|--------|----------|
| A1 | Administrador | Catálogos → Clientes → crea "Industrias ACME". | Cliente activo. |
| A2 | Almacenista | Inventario: fija un consumible con stock bajo ("Rodamiento 6203" = **5**), un consumible amplio ("Grasa" = 50) y una **herramienta** ("Torquímetro", disponible). Anota costos. | Datos listos. |
| A3 | Administrador | Usuarios → confirma sueldo vigente de Carlos, Diana y Andrés. | Necesario para el costeo. |

### Bloque B — Creación y planificación
| # | Rol | Acción | Esperado |
|---|-----|--------|----------|
| B1 | Jefe de Taller | Nueva OT: cliente ACME, tipo taller, datos de equipo, tiempo estimado **3 días**, valor proyecto **2.000.000**. | — |
| B2 | Jefe de Taller | Tarea 1 "Cambiar rodamientos" · Carlos · insumos: Rodamiento 6203 ×4 **y** Grasa ×1 (multi-insumo). | El selector muestra "disp. N" por insumo. |
| B3 | Jefe de Taller | Tarea 2 "Engrasar" · Diana · Grasa ×2. | — |
| B4 | Jefe de Taller | Guardar. | OT `OTSV-00001`, estado **En revisión**; 3 solicitudes `pendiente`; evento "creación". |
| B5 | Almacenista | Mira el menú. | Badge "Insumos para OT (3)"; campana con 1 notificación "Nueva OT / solicitudes de insumo". |
| B6 | Jefe de Taller | Botón **Planificar OT**. | Estado pasa a **Pendiente**; evento. |

### Bloque C — Bloqueo de sobre-compromiso (D1 · H1/H2)
| # | Rol | Acción | Esperado |
|---|-----|--------|----------|
| C1 | Jefe de Taller | Nueva OT para otro cliente. Tarea con Rodamiento 6203 **×4** (disp. = 1: 4 comprometidas). | Al guardar: **bloqueo** "Solo 1 disponible de «Rodamiento 6203» (4 comprometidas en OTSV-00001)". |
| C2 | Jefe de Taller | Baja la cantidad a **×1** y guarda. | Guarda; disponible pasa a 0; la opción queda deshabilitada para nuevas tareas. |

### Bloque D — Atención de Bodega, un solo paso (D2 · H10/H11)
| # | Rol | Acción | Esperado |
|---|-----|--------|----------|
| D1 | Almacenista | `/inventario/insumos-ot`. | No hay botón "Aprobar" ni pestaña "Aprobadas". Solo **Entregar** / **Rechazar**. |
| D2 | Almacenista | Entregar la línea Rodamiento ×4 de la Tarea 1 (OT-1). | Solicitud `entregada`; movimiento de salida; **stock 5 → 1**; evento `insumo_entregado`; Carlos recibe notificación "insumo disponible". |
| D3 | Almacenista | Entregar la línea Rodamiento ×1 de la OT-2. | `entregada`; stock 1 → 0. |
| D4 | Almacenista | Rechazar la Grasa ×2 de la Tarea 2 (OT-1), motivo "Se pidió de más". | `rechazada` + evento; Jefe y creador reciben notificación. |
| D5 | Jefe de Taller | Editar la Tarea 1: quitar la línea de Grasa ×1. | Evento `insumo_cancelado` en la bitácora (no desaparece en silencio). |

### Bloque E — Ejecución de tareas (H4/D3 · H6 · H14)
| # | Rol | Acción | Esperado |
|---|-----|--------|----------|
| E1 | Carlos | Abre el tablero de OT. | Solo ve OT con tareas suyas (OT-1). No ve la OT-2. |
| E2 | Carlos | Iniciar la Tarea 1. | Tarea "En curso"; OT "En curso"; evento. (Ya está planificada; si no lo estuviera, se bloquea.) |
| E3 | Carlos | Doble clic en "Finalizar" / dos pestañas. | Una sola transición registrada. |
| E4 | Carlos | Finalizar la Tarea 1, días = **2**. | Todos sus insumos entregados → finaliza directo. |
| E5 | Diana | Tarea 2: su insumo (Grasa) fue **rechazado**. Marcar "lista para finalizar". | Queda "lista para finalizar" pendiente de confirmación del Jefe; no pasa a finalizada. |
| E6 | Jefe de Taller | Resolver: reasignar insumo o confirmar sin él. **Confirmar finalización** de la Tarea 2. | Tarea "Finalizada"; evento "finalización confirmada por el Jefe". |

### Bloque F — Herramientas (D4 · H9)
| # | Rol | Acción | Esperado |
|---|-----|--------|----------|
| F1 | Jefe de Taller | OT-1 → "Herramientas asignadas" → asignar **Torquímetro**. | Herramienta `en_uso`; movimiento de salida origen `ot`; evento. |
| F2 | Jefe de Taller | Verifica el selector de **insumos** de una tarea. | El Torquímetro **no aparece** ahí (solo consumibles). |
| F3 | Jefe de Taller | Intentar continuar hacia la entrega del equipo sin devolver la herramienta. | Más adelante (G6) la entrega se **bloquea**. |

### Bloque G — Checklist, salida y entrega (H7 · H16)
| # | Rol | Acción | Esperado |
|---|-----|--------|----------|
| G1 | Jefe de Taller | OT-1 → Checklist. | Trae ítems por defecto; si faltan respuestas, mensaje "Faltan N respuestas del checklist para poder finalizar". |
| G2 | Jefe de Taller | Responder todos los ítems. | Con todas las tareas finalizadas → OT **Finalizada**; `fecha_finalizacion`; evento. |
| G3 | Jefe de Taller | "Solicitar salida". | `salida_estado = solicitada`; el Administrador recibe notificación "salida por aprobar". |
| G4 | Administrador | Abre la OT → **Rechazar** salida, motivo "Falta ajuste". | `rechazada`; OT vuelve a **En curso**; Jefe + creador notificados. |
| G5 | Jefe de Taller | Corregir (tarea nueva de Andrés, ejecutar y finalizar) → checklist → **Finalizada** → "Solicitar salida" → Administrador **Aprobar**. | `salida_estado = aprobada`; evento. |
| G6 | Jefe de Taller | Con la salida aprobada, agregar otra tarea (reabre la OT a "En curso"). Intentar **Confirmar entrega**. | La salida se **invalida** al reabrir (`salida_estado = no_solicitada`, evento). "Confirmar entrega" no está disponible / se bloquea con "la OT no está finalizada". |
| G7 | Jefe de Taller | Finalizar/cancelar esa tarea → "Finalizada" → re-solicitar y re-aprobar salida. Devolver el **Torquímetro** (estado "disponible"). Intentar entrega **sin** devolver primero. | Con herramienta pendiente: entrega **bloqueada**. Tras devolver: permitida. |
| G8 | Jefe de Taller | Escribir conformidad del cliente → **Confirmar entrega**. | OT **Entregada** (terminal); `fecha_entrega`; evento; **correo al cliente** (FR-011); creador notificado. |
| G9 | Cualquiera | Revisar la OT entregada. | Inmutable: sin editar/agregar/quitar/cancelar; no reabrible. |

### Bloque H — Costeo y utilidad (H5)
| # | Rol | Acción | Esperado |
|---|-----|--------|----------|
| H1 | Administrador | OT-1 → "Costeo y utilidad". | — |
| H2 | Administrador | Agregar mano de obra contratista (Contratista X, 300.000). | Línea agregada; evento. |
| H3 | Administrador | Revisar líneas. | Mano de obra propia = Σ días × (sueldo/30) a la fecha de finalización. **Repuestos = solo lo realmente entregado** (Rodamiento ×4); la Grasa rechazada **no** cuenta. Herramientas **no** cuentan. Utilidad = 2.000.000 − costo_total. Coincide con el cálculo manual (SC-005). |
| H4 | Administrador | Subir el sueldo de Carlos y volver al costeo. | La OT cerrada **no** recalcula. |

### Bloque I — Cancelación (D8 · H15)
| # | Rol | Acción | Esperado |
|---|-----|--------|----------|
| I1 | Jefe de Taller | Nueva OT con 2 tareas y un insumo consumible reservado. | Disponible del ítem baja. |
| I2 | Jefe de Taller | "Cancelar tarea" en una tarea pendiente, con motivo. | Tarea `cancelada`; su solicitud de insumo → `cancelada`; disponible se **libera**; evento. |
| I3 | Administrador | "Cancelar OT" con motivo. | OT `cancelada` (terminal); resto de reservas liberadas; herramientas asignadas exigen devolución; evento; no reabrible. |

### Bloque J — Vencimientos (H12/H23)
| # | Rol | Acción | Esperado |
|---|-----|--------|----------|
| J1 | Jefe de Taller | Nueva OT, tiempo estimado **1 día**, no tocarla. | — |
| J2 | Terminal | `php artisan ot:revisar-vencimientos`. | "Alertas disparadas: 1"; Jefe + Admin reciben notificación en la campana. |
| J3 | Terminal | Ejecutar el comando **otra vez**. | "Alertas disparadas: 0" (no re-notifica; `alertado_vencimiento_en` marcado). |

### Bloque K — Permisos y visibilidad (D7 · H17/H18)
| # | Rol | Acción | Esperado |
|---|-----|--------|----------|
| K1 | Vendedor | Intentar entrar a `/ordenes-trabajo`. | Sin acceso (no está en el menú; URL da 403). |
| K2 | Carlos | Abrir por URL una OT donde no tiene tareas. | 403. |
| K3 | Técnico | Ver una OT propia. | Sin "Corregir OT", sin "Costeo", sin aprobar salida. |
| K4 | Jefe de Taller | Sección de salida. | Puede solicitar, **no** aprobar. |
| K5 | Almacenista | Sin el permiso `attend-ot-insumo` (probar quitándolo). | No puede entregar/rechazar solicitudes. |

---

## 5. Riesgos y notas

- **Cambio de modelo (Fase 1)**: toca `detalle_ot`, `solicitudes_insumo_ot`, factories y ~10 tests de OT.
  Es el mayor punto de retrabajo; por eso va primero.
- **Micro-slice del spec 008**: la campana y las notificaciones que se crean aquí deben quedar
  compatibles con el spec 008 completo (mismo canal `database`, mismas clases `Notification`). Anotar en
  el spec 008 para no duplicar.
- **`navigate: false`** en todos los redirects de Livewire (convención del repo).
- **Dinero** siempre por `App\Support\Moneda::cop()`.
- **`estado_id` de la OT** lo sigue escribiendo solo `EstadoOtService` (incluido el nuevo estado
  `cancelada`).
- El scheduler debe quedar documentado y, en producción (Hostinger), configurado el cron
  `* * * * * php artisan schedule:run`.
