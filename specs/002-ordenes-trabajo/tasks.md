# Tasks: Gestión de Órdenes de Trabajo (OT)

**Input**: Design documents from `specs/002-ordenes-trabajo/` (plan.md, spec.md)

**Prerequisites**: spec 000 (Cliente, Contratista) y spec 001 (usuarios/roles) implementados; spec 004
(Técnico) recomendado antes de esta fase para poder asignar operarios reales

**Tests**: Incluidos — es el módulo central del sistema, con reglas de negocio críticas (checklist
bloqueante, máquina de estados, costeo).

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup (Shared Infrastructure)

- [ ] T001 Confirmar disponibilidad de `Cliente` (spec 000) y `Tecnico` (spec 004) en base de datos local
  (Laragon/MySQL) antes de empezar

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Bloquea las 5 historias de usuario

- [ ] T002 Migración `xxxx_create_prioridades_table.php` + seeder de niveles de prioridad
- [ ] T003 [P] Migración `xxxx_create_estados_ot_table.php` + seeder (En revisión, Pendiente, En curso,
  Finalizada, Entregada) con `es_terminal`
- [ ] T004 Migración `xxxx_create_ordenes_trabajo_table.php` (Data Model del plan.md: `numero_ot`,
  `cliente_id`, `equipo_id` nullable, `valor_proyecto` nullable, etc.)
- [ ] T005 [P] Migración `xxxx_create_detalle_ot_table.php` (tareas)
- [ ] T006 [P] Migración `xxxx_create_ot_mano_obra_contratista_table.php`
- [ ] T007 [P] Migración `xxxx_create_evidencias_ot_table.php` (con `tipo_registro` entrada/salida/proceso)
- [ ] T008 [P] Migración `xxxx_create_checklist_ot_table.php`
- [ ] T009 [P] Migración `xxxx_create_ot_eventos_table.php` (trazabilidad de correcciones/rechazos)
- [ ] T010 Modelos Eloquent: `OrdenTrabajo`, `DetalleOt`, `OtManoObraContratista`, `EvidenciaOt`,
  `ChecklistOt`, `EstadoOt`, `Prioridad`, `OtEvento` en `app/Models/`
- [ ] T011 `app/Services/OrdenTrabajo/EstadoOtService.php` — máquina de estados: transición automática
  según avance de tareas (FR-004), único punto que escribe `estado_id`
- [ ] T012 `app/Services/OrdenTrabajo/OtNumberGenerator.php` (o método en el Service) — genera
  `OTSV-00001` consecutivo (FR-014)
- [ ] T013 `app/Policies/OrdenTrabajoPolicy.php` (create, update, approveEquipmentExit — solo
  Administrador, viewCosteo — solo Administrador)

**Checkpoint**: Modelo de datos + máquina de estados listos — las historias de usuario pueden implementarse

---

## Phase 3: User Story 1 - Crear una Orden de Trabajo estructurada (Priority: P1) 🎯 MVP

**Goal**: Crear OT con cliente, al menos una tarea+técnico, numeración `OTSV-`, foto de entrada

**Independent Test**: Crear una OT con cliente y tarea con técnico, verificar estado inicial y visibilidad
en tablero

### Tests for User Story 1

- [ ] T014 [P] [US1] Feature test `tests/Feature/OrdenesTrabajo/CrearOtTest.php`: creación válida con
  numeración `OTSV-`
- [ ] T015 [P] [US1] Feature test: rechazo si no hay tarea con técnico asignado (FR-002)
- [ ] T016 [P] [US1] Feature test: tarea con insumo genera solicitud hacia Inventario (integración con
  spec 003 — usar fake/mock del `SolicitudInsumoService` si spec 003 aún no está completo)

### Implementation for User Story 1

- [ ] T017 [US1] `app/Services/OrdenTrabajo/SolicitudInsumoService.php` (interfaz hacia spec 003)
- [ ] T018 [US1] Componente Volt `app/Livewire/OrdenesTrabajo/Crear.php` (Ilustración 13, multi-sección:
  cliente/equipo, descripción, tareas)
- [ ] T019 [US1] Captura de registro fotográfico de entrada + estado de ingreso del equipo (FR-017)
- [ ] T020 [US1] Ruta `/ordenes-trabajo/crear` protegida por `OrdenTrabajoPolicy@create`
- [ ] T021 [US1] Notificación de confirmación de creación (Ilustración 14)

**Checkpoint**: Se pueden crear OT reales — MVP del módulo

---

## Phase 4: User Story 2 - Ejecutar y actualizar el flujo de la OT (Priority: P1)

**Goal**: Iniciar/finalizar tareas, cargar evidencias, transición automática de estado, comparativo tiempo
estimado vs. real

**Independent Test**: Iniciar una tarea de una OT registrada, agregar evidencia, finalizar, verificar
transición automática sin edición manual del estado

### Tests for User Story 2

- [ ] T022 [P] [US2] Feature test `tests/Feature/OrdenesTrabajo/FlujoEstadoTest.php`: Registrada → En curso
  al iniciar primera tarea
- [ ] T023 [P] [US2] Feature test: En curso → Finalizada solo si todas las tareas y el checklist están
  completos
- [ ] T024 [P] [US2] Feature test: comparativo tiempo estimado vs. real se calcula correctamente

### Implementation for User Story 2

- [ ] T025 [US2] Componente Volt `app/Livewire/OrdenesTrabajo/Detalle.php` (Ilustración 15: tareas,
  evidencias, checklist, comentarios)
- [ ] T026 [US2] Acción "Iniciar tarea" / "Finalizar tarea" invocando `EstadoOtService`
- [ ] T027 [US2] Carga de evidencias (imagen/documento) con `tipo_registro = proceso`
- [ ] T028 [US2] Ruta `/ordenes-trabajo/{ot}`

**Checkpoint**: US1 + US2 funcionan de forma independiente — ejecución operativa completa

---

## Phase 5: User Story 3 - Checklist de cierre y entrega al cliente (Priority: P2)

**Goal**: Checklist bloqueante antes de Finalizada; flujo de salida de equipo con aprobación administrativa

**Independent Test**: Intentar finalizar sin checklist completo (bloquea), completar checklist, solicitar
salida, aprobar/rechazar, confirmar entrega

### Tests for User Story 3

- [ ] T029 [P] [US3] Feature test `tests/Feature/OrdenesTrabajo/ChecklistCierreTest.php`: bloqueo con
  checklist incompleto (SC-003)
- [ ] T030 [P] [US3] Feature test `tests/Feature/OrdenesTrabajo/EntregaEquipoTest.php`: aprobación →
  Entregada con evidencia de salida y notificación al cliente (evento, capturable con `Event::fake()`)
- [ ] T031 [P] [US3] Feature test: rechazo de salida → OT vuelve a "En curso" con motivo en `ot_eventos`
  (FR-013)

### Implementation for User Story 3

- [ ] T032 [US3] UI de checklist en `Detalle.php` (marcar ítems, bloquear botón "Finalizar" si incompleto)
- [ ] T033 [US3] Flujo de solicitud/aprobación/rechazo de salida de equipo (`OrdenTrabajoPolicy@
  approveEquipmentExit`)
- [ ] T034 [US3] Registro de evidencia de salida + firma de conformidad opcional (`firma_cliente_url`)
- [ ] T035 [US3] Evento `OtEntregada` disparado al confirmar entrega (consumido luego por spec 008)

**Checkpoint**: Ciclo completo de OT (crear → ejecutar → cerrar → entregar) funcional

---

## Phase 6: User Story 4 - Corregir una OT sin romper trazabilidad (Priority: P3)

**Goal**: Administrador/Jefe de Taller corrigen una OT en curso, con historial en `ot_eventos`

**Independent Test**: Reasignar técnico de una tarea en curso, verificar registro en trazabilidad sin
pérdida de datos previos

### Tests for User Story 4

- [ ] T036 [P] [US4] Feature test `tests/Feature/OrdenesTrabajo/CorreccionOtTest.php`: reasignación de
  tarea queda en `ot_eventos`; corrección de descripción/prioridad no altera evidencias/tareas completadas

### Implementation for User Story 4

- [ ] T037 [US4] Acciones de corrección en `Detalle.php` (reasignar técnico, editar descripción/prioridad)
  restringidas a Administrador/Jefe de Taller
- [ ] T038 [US4] Registro automático en `ot_eventos` (tipo `correccion`) en cada corrección

**Checkpoint**: US1-US4 funcionan de forma independiente

---

## Phase 7: User Story 5 - Costeo y utilidad neta por OT (Priority: P2)

**Goal**: Cálculo automático de costo total y utilidad neta (mano de obra propia + contratista + repuestos
vs. valor cobrado)

**Independent Test**: Con datos conocidos de mano de obra, contratista y repuestos, comparar el cálculo del
sistema contra el mismo caso resuelto manualmente en el Excel real (oráculo, SC-005)

### Tests for User Story 5

- [ ] T039 [P] [US5] Feature test `tests/Feature/OrdenesTrabajo/CosteoUtilidadTest.php`: costo de mano de
  obra propia (días trabajados × valor día del técnico = `sueldo/30` vigente a la fecha de referencia de la
  OT, spec 004 FR-009..FR-011; incluir caso "OT cerrada no se recostea al cambiar el sueldo")
- [ ] T040 [P] [US5] Feature test: costo de contratistas se suma correctamente al costo total
- [ ] T041 [P] [US5] Feature test: costo de repuestos (integración con spec 003, `costo_unitario`) se suma
  correctamente
- [ ] T042 [P] [US5] Feature test: utilidad neta = valor_proyecto - costo_total, incluyendo caso
  `valor_proyecto = null`

### Implementation for User Story 5

- [ ] T043 [US5] `app/Services/OrdenTrabajo/CosteoOtService.php` (fórmula documentada en plan.md — DEBE
  quedar aislado de la UI para reutilizarse en spec 007)
- [ ] T044 [US5] Componente Volt `app/Livewire/OrdenesTrabajo/Costeo.php` (vista solo Administrador)
- [ ] T045 [US5] UI para agregar mano de obra de contratista a una OT (usa `Contratista` de spec 000)

**Checkpoint**: Las 5 historias de usuario son funcionales de forma independiente

---

## Phase 8: Polish & Cross-Cutting Concerns

- [ ] T046 [P] Componente Volt `app/Livewire/OrdenesTrabajo/Tablero.php` (Ilustración 16: listado +
  filtros por estado/cliente/tipo de servicio/fechas/responsable, SC-004)
- [ ] T047 Índices de base de datos en `estado_id`, `cliente_id`, `fecha_creacion` de `ordenes_trabajo`
- [ ] T048 Evento `OtProximaAVencer` (FR-010) disparado por un job programado que evalúa el umbral desde
  `CONFIGURACIONES` (consumido por spec 008)
- [ ] T049 Ejecutar `php artisan test --filter=OrdenesTrabajo` en verde

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
