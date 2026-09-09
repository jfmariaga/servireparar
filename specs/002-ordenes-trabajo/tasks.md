# Tasks: Gestión de Órdenes de Trabajo (OT)

**Input**: Design documents from `specs/002-ordenes-trabajo/` (plan.md, spec.md)

**Prerequisites**: spec 000 (Cliente, Contratista) y spec 001 (usuarios/roles) implementados; spec 004
(Técnico) recomendado antes de esta fase para poder asignar operarios reales

**Tests**: Incluidos — es el módulo central del sistema, con reglas de negocio críticas (checklist
bloqueante, máquina de estados, costeo).

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 Confirmar disponibilidad de `Cliente` (spec 000) y `Tecnico` (spec 004) en base de datos local
  (Laragon/MySQL) antes de empezar

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Bloquea las 5 historias de usuario

- [X] T002 Migración `xxxx_create_prioridades_table.php` + seeder de niveles de prioridad
- [X] T003 [P] Migración `xxxx_create_estados_ot_table.php` + seeder (En revisión, Pendiente, En curso,
  Finalizada, Entregada) con `es_terminal`
- [X] T004 Migración `xxxx_create_ordenes_trabajo_table.php` (Data Model del plan.md: `numero_ot`,
  `cliente_id`, `equipo_id` nullable, `valor_proyecto` nullable, etc.)
- [X] T005 [P] Migración `xxxx_create_detalle_ot_table.php` (tareas)
- [X] T006 [P] Migración `xxxx_create_ot_mano_obra_contratista_table.php`
- [X] T007 [P] Migración `xxxx_create_evidencias_ot_table.php` (con `tipo_registro` entrada/salida/proceso)
- [X] T008 [P] Migración `xxxx_create_checklist_ot_table.php`
- [X] T009 [P] Migración `xxxx_create_ot_eventos_table.php` (trazabilidad de correcciones/rechazos)
- [X] T010 Modelos Eloquent: `OrdenTrabajo`, `DetalleOt`, `OtManoObraContratista`, `EvidenciaOt`,
  `ChecklistOt`, `EstadoOt`, `Prioridad`, `OtEvento` en `app/Models/`
- [X] T011 `app/Services/OrdenTrabajo/EstadoOtService.php` — máquina de estados: transición automática
  según avance de tareas (FR-004), único punto que escribe `estado_id`
- [X] T012 `app/Services/OrdenTrabajo/OtNumberGenerator.php` (o método en el Service) — genera
  `OTSV-00001` consecutivo (FR-014)
- [X] T013 `app/Policies/OrdenTrabajoPolicy.php` (create, update, approveEquipmentExit — solo
  Administrador, viewCosteo — solo Administrador)

**Checkpoint**: Modelo de datos + máquina de estados listos — las historias de usuario pueden implementarse

---

## Phase 3: User Story 1 - Crear una Orden de Trabajo estructurada (Priority: P1) 🎯 MVP

**Goal**: Crear OT con cliente, al menos una tarea+técnico, numeración `OTSV-`, foto de entrada

**Independent Test**: Crear una OT con cliente y tarea con técnico, verificar estado inicial y visibilidad
en tablero

### Tests for User Story 1

- [X] T014 [P] [US1] Feature test `tests/Feature/OrdenesTrabajo/CrearOtTest.php`: creación válida con
  numeración `OTSV-`
- [X] T015 [P] [US1] Feature test: rechazo si no hay tarea con técnico asignado (FR-002)
- [X] T016 [P] [US1] Feature test: tarea con insumo genera solicitud hacia Inventario (integración con
  spec 003 — usar fake/mock del `SolicitudInsumoService` si spec 003 aún no está completo)

### Implementation for User Story 1

- [X] T017 [US1] `app/Services/OrdenTrabajo/SolicitudInsumoService.php` (interfaz hacia spec 003)
- [X] T018 [US1] Componente Volt `app/Livewire/OrdenesTrabajo/Crear.php` (Ilustración 13, multi-sección:
  cliente/equipo, descripción, tareas)
- [X] T019 [US1] Captura de registro fotográfico de entrada + estado de ingreso del equipo (FR-017)
- [X] T020 [US1] Ruta `/ordenes-trabajo/crear` protegida por `OrdenTrabajoPolicy@create`
- [X] T021 [US1] Notificación de confirmación de creación (Ilustración 14)

**Checkpoint**: Se pueden crear OT reales — MVP del módulo

---

## Phase 4: User Story 2 - Ejecutar y actualizar el flujo de la OT (Priority: P1)

**Goal**: Iniciar/finalizar tareas, cargar evidencias, transición automática de estado, comparativo tiempo
estimado vs. real

**Independent Test**: Iniciar una tarea de una OT registrada, agregar evidencia, finalizar, verificar
transición automática sin edición manual del estado

### Tests for User Story 2

- [X] T022 [P] [US2] Feature test `tests/Feature/OrdenesTrabajo/FlujoEstadoTest.php`: Registrada → En curso
  al iniciar primera tarea
- [X] T023 [P] [US2] Feature test: En curso → Finalizada solo si todas las tareas y el checklist están
  completos
- [X] T024 [P] [US2] Feature test: comparativo tiempo estimado vs. real se calcula correctamente

### Implementation for User Story 2

- [X] T025 [US2] Componente Volt `app/Livewire/OrdenesTrabajo/Detalle.php` (Ilustración 15: tareas,
  evidencias, checklist, comentarios)
- [X] T026 [US2] Acción "Iniciar tarea" / "Finalizar tarea" invocando `EstadoOtService`
- [X] T027 [US2] Carga de evidencias (imagen/documento) con `tipo_registro = proceso`
- [X] T028 [US2] Ruta `/ordenes-trabajo/{ot}`

**Checkpoint**: US1 + US2 funcionan de forma independiente — ejecución operativa completa

---

## Phase 5: User Story 3 - Checklist de cierre y entrega al cliente (Priority: P2)

**Goal**: Checklist bloqueante antes de Finalizada; flujo de salida de equipo con aprobación administrativa

**Independent Test**: Intentar finalizar sin checklist completo (bloquea), completar checklist, solicitar
salida, aprobar/rechazar, confirmar entrega

### Tests for User Story 3

- [X] T029 [P] [US3] Feature test `tests/Feature/OrdenesTrabajo/ChecklistCierreTest.php`: bloqueo con
  checklist incompleto (SC-003)
- [X] T030 [P] [US3] Feature test `tests/Feature/OrdenesTrabajo/EntregaEquipoTest.php`: aprobación →
  Entregada con evidencia de salida y notificación al cliente (evento, capturable con `Event::fake()`)
- [X] T031 [P] [US3] Feature test: rechazo de salida → OT vuelve a "En curso" con motivo en `ot_eventos`
  (FR-013)

### Implementation for User Story 3

- [X] T032 [US3] UI de checklist en `Detalle.php` (marcar ítems, bloquear botón "Finalizar" si incompleto)
- [X] T033 [US3] Flujo de solicitud/aprobación/rechazo de salida de equipo (`OrdenTrabajoPolicy@
  approveEquipmentExit`)
- [X] T034 [US3] Registro de evidencia de salida + firma de conformidad opcional (`firma_cliente_url`)
- [X] T035 [US3] Evento `OtEntregada` disparado al confirmar entrega (consumido luego por spec 008)

**Checkpoint**: Ciclo completo de OT (crear → ejecutar → cerrar → entregar) funcional

---

## Phase 6: User Story 4 - Corregir una OT sin romper trazabilidad (Priority: P3)

**Goal**: Administrador/Jefe de Taller corrigen una OT en curso, con historial en `ot_eventos`

**Independent Test**: Reasignar técnico de una tarea en curso, verificar registro en trazabilidad sin
pérdida de datos previos

### Tests for User Story 4

- [X] T036 [P] [US4] Feature test `tests/Feature/OrdenesTrabajo/CorreccionOtTest.php`: reasignación de
  tarea queda en `ot_eventos`; corrección de descripción/prioridad no altera evidencias/tareas completadas

### Implementation for User Story 4

- [X] T037 [US4] Acciones de corrección en `Detalle.php` (reasignar técnico, editar descripción/prioridad)
  restringidas a Administrador/Jefe de Taller
- [X] T038 [US4] Registro automático en `ot_eventos` (tipo `correccion`) en cada corrección

**Checkpoint**: US1-US4 funcionan de forma independiente

---

## Phase 7: User Story 5 - Costeo y utilidad neta por OT (Priority: P2)

**Goal**: Cálculo automático de costo total y utilidad neta (mano de obra propia + contratista + repuestos
vs. valor cobrado)

**Independent Test**: Con datos conocidos de mano de obra, contratista y repuestos, comparar el cálculo del
sistema contra el mismo caso resuelto manualmente en el Excel real (oráculo, SC-005)

### Tests for User Story 5

- [X] T039 [P] [US5] Feature test `tests/Feature/OrdenesTrabajo/CosteoUtilidadTest.php`: costo de mano de
  obra propia (días trabajados × valor día del técnico = `sueldo/30` vigente a la fecha de referencia de la
  OT, spec 004 FR-009..FR-011; incluir caso "OT cerrada no se recostea al cambiar el sueldo")
- [X] T040 [P] [US5] Feature test: costo de contratistas se suma correctamente al costo total
- [X] T041 [P] [US5] Feature test: costo de repuestos (integración con spec 003, `costo_unitario`) se suma
  correctamente
- [X] T042 [P] [US5] Feature test: utilidad neta = valor_proyecto - costo_total, incluyendo caso
  `valor_proyecto = null`

### Implementation for User Story 5

- [X] T043 [US5] `app/Services/OrdenTrabajo/CosteoOtService.php` (fórmula documentada en plan.md — DEBE
  quedar aislado de la UI para reutilizarse en spec 007)
- [X] T044 [US5] Componente Volt `app/Livewire/OrdenesTrabajo/Costeo.php` (vista solo Administrador)
- [X] T045 [US5] UI para agregar mano de obra de contratista a una OT (usa `Contratista` de spec 000)

**Checkpoint**: Las 5 historias de usuario son funcionales de forma independiente

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T046 [P] Componente Volt `app/Livewire/OrdenesTrabajo/Tablero.php` (Ilustración 16: listado +
  filtros por estado/cliente/tipo de servicio/fechas/responsable, SC-004)
- [X] T047 Índices de base de datos en `estado_id`, `cliente_id`, `fecha_creacion` de `ordenes_trabajo`
- [X] T048 Evento `OtProximaAVencer` (FR-010) disparado por un job programado que evalúa el umbral desde
  `CONFIGURACIONES` (consumido por spec 008)
- [X] T049 Ejecutar `php artisan test --filter=OrdenesTrabajo` en verde

---

## Dependencies & Execution Order

- **Setup + Foundational** bloquean todo — máquina de estados y numeración deben existir primero.
- **US1** es el MVP real del módulo (sin crear OT nada más tiene sentido).
- **US2** depende de US1 (necesita OT creadas para ejecutar tareas).
- **US3** depende de US2 (necesita tareas finalizables para llegar al cierre).
- **US4** es independiente de US3/US5, solo depende de US1/US2 (una OT en curso que corregir).
- **US5** depende de US2 (días trabajados) y de spec 000/003/004 para tener contratistas/insumos/sueldos
  reales, pero puede desarrollarse con datos de prueba en paralelo a US3/US4.

## Implementation Strategy

MVP = US1 + US2 (crear y ejecutar OT). US3 (checklist/entrega) es el segundo incremento crítico porque
cierra el ciclo de valor. US5 (costeo) puede entregarse en un incremento separado sin bloquear el uso
operativo diario del módulo.

---

## Notas de implementación (2026-09-07 — `/speckit-implement`)

Todas las tareas T001–T049 implementadas y en verde (`php artisan test --filter=OrdenesTrabajo` →
32 tests). Suite global: 148 en verde.

Desviaciones respecto al plan, coherentes con las convenciones del repo ya implementadas (specs 003/004):

- **Componentes Volt como archivos únicos** en `resources/views/livewire/ordenes-trabajo/`
  (`tablero`, `crear`, `detalle`, `costeo`), no clases en `app/Livewire/OrdenesTrabajo/` — el proyecto
  usa Volt SFC en todos los módulos.
- **Servicios**: además de `EstadoOtService` / `CosteoOtService` / `SolicitudInsumoService` (plan), se
  añadieron `OrdenTrabajoService` (orquesta creación/corrección, US1/US4), `OtNumberGenerator`
  (consecutivo `OTSV-`), `SalidaEquipoService` (sub-flujo FR-008/FR-013) y `VencimientoOtService`
  (FR-010). Todos aislados de la UI.
- **Sub-flujo de salida de equipo** modelado con columnas `salida_*` en `ordenes_trabajo` (no un
  estado más en `estados_ot`), manteniendo la máquina de estados principal en los 5 estados del spec.
  El rechazo reabre a `en_curso` vía `EstadoOtService::reabrir()`.
- **Integración con Bodega (FR-003)**: tabla propia `solicitudes_insumo_ot` (entidad `SOLICITUDES_INSUMO`
  del ER) que spec 003 US1 consumirá para su pantalla de atención del Almacenista. Crear la OT NO
  descuenta stock.
- **Eventos de dominio** para spec 008: `OtCreada`, `OtEntregada`, `OtProximaAVencer` (+ comando
  `ot:revisar-vencimientos` agendado a diario en `routes/console.php`).
- **Permisos** (`RolesSeeder`): `manage-ot` (Administrador, Jefe de Taller) y `execute-ot` (Técnico,
  Jefe de Taller, Administrador). Aprobar salida de equipo y ver costeo: solo Administrador
  (`OrdenTrabajoPolicy`).
- **Catálogos**: `PrioridadesSeeder` (Baja/Media/Alta/Urgente) y `EstadosOtSeeder` (en_revision,
  pendiente, en_curso, finalizada, entregada — solo `entregada` terminal) añadidos a `DatabaseSeeder`.
- **Umbral de vencimiento** en `config/ot.php` (`dias_umbral_vencimiento`, env
  `OT_DIAS_UMBRAL_VENCIMIENTO`, default 2).

Pendiente operativo: correr `php artisan db:seed --class=RolesSeeder --force` +
`php artisan permission:cache-reset` + `db:seed --class=PrioridadesSeeder` + `db:seed --class=EstadosOtSeeder`
en cada entorno con datos previos.

---

## Phase 11: Endurecimiento del flujo (2026-09-08)

Refuerzo del flujo tras la auditoría del 2026-09-08 (hallazgos H1–H23) + micro-slice del spec 008
(campana in-app). Trazabilidad completa, decisiones de negocio y protocolo de pruebas de aceptación en
[`docs/PLAN-ENDURECIMIENTO-OT-2026-09-08.md`](../../docs/PLAN-ENDURECIMIENTO-OT-2026-09-08.md).

Decisiones del cliente: D1 bloqueo duro de stock al guardar la tarea · D2 Bodega de un solo paso (sin
"aprobar") · D3 finalizar tarea con insumo no entregado = confirmación del Jefe · D4 herramientas en la
OT con asignación + devolución · D5 campana in-app con polling (~45 s) · D6 multi-insumo por tarea ·
D7 el Técnico solo ve sus OT · D8 cancelar OT / cancelar tarea.

### Fase 0 — Base
- [x] T050 Commit del spec 002 actual (working tree) como respaldo del Hito 2; `php artisan test` baseline en verde

### Fase 1 — Multi-insumo por tarea (D6 · H8)
- [x] T051 Migración `create_detalle_ot_insumos_table` (`detalle_ot_id`, `inventario_id`, `cantidad`; unique `(detalle_ot_id, inventario_id)`)
- [x] T052 Migración de datos `detalle_ot.insumo_id`/`cantidad_insumo` → `detalle_ot_insumos`
- [x] T053 Modelo `DetalleOtInsumo` + `DetalleOt::insumos()`; reemplazar `requiereInsumo()` por `tieneInsumos()`/`lineasInsumo()`
- [x] T054 `solicitudes_insumo_ot.detalle_ot_insumo_id` (1 solicitud por línea) + migración de datos
- [x] T055 `SolicitudInsumoService::sincronizarDesdeTarea()` itera líneas: alta/cambio/baja con evento en `ot_eventos` (H11/H21)
- [x] T056 UI del formulario de tarea (`crear`/`detalle`): repetidor de insumos
- [x] T057 `CosteoOtService` soporta N líneas de insumo por tarea
- [x] T058 [P] Ajustar tests/factories de OT al nuevo modelo; `DetalleOtInsumoFactory`

### Fase 2 — Reserva y bloqueo de stock (D1 · H1, H2, H5)
- [x] T059 `Inventario::comprometido()`/`disponible()` (= `stock_actual − Σ solicitudes pendiente`); scope `SolicitudInsumoOt::pendientesDe()`
- [x] T060 Bloqueo duro en `OrdenTrabajoService` (crear/agregarTarea/actualizarTarea) si `cantidad > disponible()`; castear `stock_actual` a float (H20)
- [x] T061 `<x-select>` de insumos con "disp. N"; opciones sin disponible deshabilitadas; badge junto a cantidad
- [x] T062 Panel "Insumos de la OT" consolidado en `detalle.blade.php` + enlace a `/inventario/insumos-ot?ot=` (H19)
- [x] T063 `CosteoOtService`: repuestos = solo `entregada` (+ no entregadas "estimado"); excluir `rechazada`/`cancelada` (H5)
- [x] T064 [P] Tests: reserva descuenta disponible; 2.ª OT no sobre-compromete; costeo ignora rechazadas

### Fase 3 — Bodega de un solo paso (D2 · H10, H11)
- [x] T065 `AtencionInsumoOtService`: eliminar `aprobar()`; enum estado → `('pendiente','entregada','rechazada')` + migración de datos
- [x] T066 `inventario/solicitudes-ot.blade.php`: quitar "Aprobar"/"Aprobadas"; disponible vs solicitado con alerta
- [x] T067 Eventos `insumo_cancelado`/`insumo_modificado` en `ot_eventos` al editar tareas
- [x] T068 [P] Tests: sin paso aprobar; entregar desde pendiente; rechazo con evento; edición registra cambio

### Fase 4 — Herramientas en la OT (D4 · H9)
- [x] T069 Migración `create_ot_herramientas_table` (asignación + devolución + `movimiento_*_id`)
- [x] T070 `OtHerramientaService`: `asignar()` (valida disponible, `MovimientoService::salida` origen `ot`) / `devolver()` (`MovimientoService::devolucion`)
- [x] T071 UI "Herramientas asignadas" en `detalle.blade.php`; selector de insumos filtrado a `tipo=consumible`
- [x] T072 Guardia: no confirmar entrega del equipo con herramientas sin devolver
- [x] T073 `CosteoOtService`: herramientas asignadas no suman a repuestos
- [x] T074 [P] Tests: asignar/devolver; entrega bloqueada; herramienta fuera del costeo

### Fase 5 — Máquina de estados y guardias (H4/D3, H6, H7, H14, H15/D8)
- [x] T075 Botón "Planificar OT" (Jefe) → `EstadoOtService::planificar()` en `detalle.blade.php`
- [x] T076 `executeTareas` + `iniciarTarea`: exigir `estado ∈ {pendiente, en_curso}`
- [x] T077 Confirmación del Jefe para finalizar tarea con insumo no entregado (`confirmarFinalizacionTarea`, `manage-ot`)
- [x] T078 `SalidaEquipoService::confirmarEntrega()`: exigir `ot.estado === finalizada`
- [x] T079 `reabrir()` + reapertura por `agregarTarea`: resetear `salida_*` + evento "salida invalidada"
- [x] T080 Updates condicionales (`where estado_tarea`) en `iniciarTarea`/`finalizarTarea`/`responderChecklist` (H14)
- [x] T081 Estado `cancelada` (OT terminal + tarea); `cancelarTarea()`/`cancelarOt()` liberan reservas y exigen devolución de herramientas; UI + guardas de cascada (H22)
- [x] T082 [P] Tests: planificación obligatoria; entrega exige finalizada; reapertura invalida salida; cancelar libera reservas

### Fase 6 — Notificaciones in-app + alertas (D5 · H3, H12, H23)
- [x] T083 Migración `notifications` + `Notifiable` en `User`; `config/ot.php` `notif_poll_segundos` (45)
- [x] T084 Notifications: OtCreada→Jefes, SolicitudCreada→Almacén, SolicitudRechazada→Jefe+creador, SolicitudEntregada→técnico, SalidaSolicitada→Admins, SalidaRechazada→Jefe+creador, OtEntregada→creador, OtProximaAVencer→Jefe+Admin, StockBajo→Almacén
- [x] T085 Listeners para `OtCreada`/`OtEntregada`/`OtProximaAVencer`/`StockBajo` + disparo directo desde servicios sin evento
- [x] T086 `OtEntregada` → correo al cliente (FR-011): Mailable + plantilla
- [x] T087 Componente Volt `notificaciones-campana` (badge no leídas, dropdown, `wire:poll`) en el layout
- [x] T088 Badges en `nav-items.blade.php`: Insumos para OT (N) / Salidas por aprobar (N) / OT por planificar (N)
- [x] T089 Anti-spam de vencimientos: `ordenes_trabajo.alertado_vencimiento_en` (H23)
- [x] T090 README + bitácora: sección "Scheduler" (`schedule:work` dev, cron `schedule:run` prod) (H12)
- [x] T091 [P] Tests: cada notificación al rol correcto; campana lista no leídas; vencimiento no re-notifica

### Fase 7 — Roles, visibilidad, checklist (D7 · H16, H17, H18)
- [x] T092 `OrdenTrabajoPolicy::view` + scope `OrdenTrabajo::visiblesPara(User)` (Técnico solo sus OT); aplicar en `tablero`/`detalle`
- [x] T093 Permiso `attend-ot-insumo` (Almacenista, Admin) en `RolesSeeder`; usarlo en `inventario/solicitudes-ot.blade.php` (H18)
- [x] T094 Checklist genérico por defecto (`config/ot.php` `checklist_por_defecto`) + mensaje "Faltan N respuestas…" (H16)
- [x] T095 [P] Tests: técnico no ve OT ajena (403); almacenista sin permiso no atiende; checklist por defecto presente

### Fase 8 — Regresión y cierre
- [x] T096 `php artisan test` completo en verde; actualizar contador en README y bitácora
- [x] T097 `docs/BITACORA-2026-09-08.md` + marcar T050–T098 en este archivo
- [ ] T098 Ejecutar el Protocolo de pruebas de aceptación (PLAN §4) con los 5 roles; registrar resultados

---

## Notas de implementación (2026-09-08 — Phase 11 endurecimiento)

T050–T097 implementadas en la rama `feature/ot-endurecimiento` (13 commits). **Suite: 197 tests en
verde** (159 → +38). Detalle en `docs/BITACORA-2026-09-08.md`; hallazgos, decisiones y protocolo de
pruebas en `docs/PLAN-ENDURECIMIENTO-OT-2026-09-08.md`.

Cambios de modelo: `detalle_ot_insumos` (multi-insumo), `ot_herramientas`, `notifications`; se
eliminan `detalle_ot.insumo_id`/`cantidad_insumo`; se quita `aprobada` de `solicitudes_insumo_ot`
(Bodega de un paso); nuevo estado `cancelada` (OT terminal + tarea) y columnas
`detalle_ot.finalizacion_solicitada_en`, `ordenes_trabajo.alertado_vencimiento_en`.

Pendiente: **T098** (protocolo de pruebas de aceptación con los 5 roles — sesión conjunta con el
cliente). Operativo en entornos con datos: re-seed de `RolesSeeder` (permiso `attend-ot-insumo`) +
`permission:cache-reset`, y re-seed de `EstadosOtSeeder` (estado `cancelada`).
