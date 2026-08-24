# Feature Specification: Gestión de Órdenes de Trabajo (OT)

**Feature Branch**: `002-ordenes-trabajo`

**Created**: 2026-08-24

**Status**: Draft

**Input**: Cotización SERVIREPARAR, Módulo 1 — Gestión de Órdenes de Trabajo (OT); Fase 2 del cronograma;
Mockups Ilustraciones 12-16 (Flujo #2, Jefe de Taller); ER: `ORDENES_TRABAJO`, `DETALLE_OT`, `EVIDENCIAS_OT`,
`CHECKLIST_OT`, `ESTADOS_OT`, `PRIORIDADES`, `CLIENTES`, `EQUIPOS`, `TECNICOS`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Crear una Orden de Trabajo estructurada (Priority: P1)

El Jefe de Taller registra una nueva OT con cliente, equipo (si aplica), tipo de servicio (taller /
domicilio), descripción, prioridad, tiempo estimado y al menos una tarea con su operario (técnico)
asignado e insumos requeridos.

**Why this priority**: Es el núcleo de la operación de SERVIREPARAR; ningún otro flujo del módulo (OT) es
posible sin esto, y es el sistema central de la operación según la cotización.

**Independent Test**: Se crea una OT con un cliente y una tarea con técnico asignado, y se verifica que
queda en estado "Registrada"/"En revisión" y visible en el tablero de OT.

**Acceptance Scenarios**:

1. **Given** el Jefe de Taller autenticado, **When** completa el formulario de OT con cliente, descripción,
   prioridad, tiempo estimado y al menos una tarea con operario asignado, **Then** el sistema crea la OT
   con un número consecutivo y estado inicial, mostrando confirmación (Ilustración 14).
2. **Given** un formulario de OT sin ninguna tarea con operario asignado, **When** se intenta crear,
   **Then** el sistema rechaza la creación (regla de negocio: toda OT nace con al menos una tarea asignada).
3. **Given** una OT que requiere insumos para una tarea, **When** se guarda la tarea con insumo
   seleccionado, **Then** se genera automáticamente una solicitud de insumo hacia el módulo de Inventario
   (ver spec 003), visible por el Almacenista.

---

### User Story 2 - Ejecutar y actualizar el flujo de la OT (Priority: P1)

El Jefe de Taller y los técnicos asignados avanzan las tareas de la OT (iniciar, agregar evidencia,
finalizar), y el sistema recalcula automáticamente el estado global de la OT en función del avance de sus
tareas.

**Why this priority**: Es el valor operativo diario del sistema — seguimiento en tiempo real del trabajo en
taller, requerido explícitamente como "control de tiempos" y "estados del flujo" en la cotización.

**Independent Test**: Se inicia una tarea de una OT registrada, se agrega evidencia, se finaliza, y se
verifica que el estado de la OT transiciona automáticamente sin edición manual del campo estado.

**Acceptance Scenarios**:

1. **Given** una OT en estado "Registrada" con tareas pendientes, **When** se inicia la primera tarea,
   **Then** la OT pasa automáticamente a "En curso"/"En progreso".
2. **Given** todas las tareas de una OT marcadas como finalizadas y el checklist de cierre aprobado,
   **When** se completa la última tarea, **Then** la OT pasa a "Finalizada" automáticamente.
3. **Given** una tarea en curso, **When** el técnico o Jefe de Taller carga una imagen o documento como
   evidencia, **Then** la evidencia queda asociada a la OT/tarea con fecha de subida (Ilustración 15).
4. **Given** una OT con tiempo estimado definido, **When** se registra la fecha real de finalización,
   **Then** el sistema calcula y expone el comparativo tiempo estimado vs. real.

---

### User Story 3 - Checklist de cierre y entrega al cliente (Priority: P2)

Antes de cerrar una OT, el sistema exige completar un checklist de validación de calidad; una vez
finalizada, se gestiona la solicitud de salida del equipo, aprobación administrativa y confirmación de
entrega al cliente.

**Why this priority**: Es una "funcionalidad diferencial" explícita de la cotización (checklist obligatorio
antes del cierre) y cierra el ciclo completo de valor de la OT.

**Independent Test**: Se intenta finalizar una OT sin checklist completo (debe bloquear) y luego con
checklist completo (debe permitir avanzar a flujo de entrega).

**Acceptance Scenarios**:

1. **Given** una OT con todas las tareas completadas pero checklist incompleto, **When** se intenta marcar
   como Finalizada, **Then** el sistema bloquea el cierre hasta que todos los ítems del checklist estén
   marcados.
2. **Given** una OT finalizada con equipo asociado, **When** se solicita la salida del equipo, **Then** el
   sistema requiere aprobación administrativa antes de permitir el registro de entrega.
3. **Given** una OT aprobada para salida, **When** se confirma la entrega, **Then** la OT pasa a estado
   "Entregada" y el cliente recibe notificación automática (ver spec 008).

---

### User Story 4 - Corregir una OT sin romper trazabilidad (Priority: P3)

El Administrador o el Jefe de Taller pueden ajustar una OT ya iniciada (tareas, responsables, insumos)
manteniendo un historial de los cambios realizados.

**Why this priority**: Funcionalidad diferencial explícita de la cotización; menos frecuente que crear/
ejecutar OT pero crítica quan ocurre un error operativo.

**Independent Test**: Se modifica el técnico asignado a una tarea de una OT en curso y se verifica que el
cambio queda registrado en el historial/comentarios de la OT sin perder el estado ni evidencias previas.

**Acceptance Scenarios**:

1. **Given** una OT en curso, **When** el Jefe de Taller reasigna una tarea a otro técnico, **Then** el
   cambio se aplica y queda registrado como comentario/evento en la trazabilidad de la OT.
2. **Given** una OT en curso, **When** el Administrador corrige la descripción o prioridad, **Then** el
   cambio se aplica sin alterar las evidencias ni tareas ya completadas.

### Edge Cases

- ¿Qué sucede si se elimina/desactiva un técnico que tiene tareas activas asignadas en una OT? Debe
  impedirse o requerir reasignación previa.
- ¿Qué pasa con una OT cuyo tiempo estimado vence y aún tiene tareas pendientes? Debe generar alerta (ver
  spec 008) sin bloquear la ejecución.
- Umbral exacto para "próxima a vencer" vs. "vencida": [NEEDS CLARIFICATION: la cotización pide "alertas por
  vencimiento" pero no define el umbral (ej. 24h antes del tiempo estimado); se requiere definición con el
  cliente].
- Flujo de aprobación administrativa para salida de equipo: [NEEDS CLARIFICATION: ¿quién puede aprobar
  (solo rol Administrador) y qué pasa si se rechaza la salida — vuelve la OT a "Finalizada" en espera, o se
  reabre?].
- ¿Puede una OT tener más de un equipo asociado, o es siempre 0 o 1 equipo por OT? El ER modela
  `equipo_id` como FK simple en `ORDENES_TRABAJO`, sugiriendo 0..1.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE permitir crear una OT con cliente, equipo (opcional), tipo de servicio
  (taller/domicilio), descripción, prioridad y tiempo estimado.
- **FR-002**: El sistema DEBE exigir al menos una tarea con un técnico responsable asignado como condición
  para crear la OT.
- **FR-003**: El sistema DEBE permitir asociar insumos (herramientas/consumibles) a cada tarea, generando
  automáticamente una solicitud hacia el módulo de Inventario.
- **FR-004**: El sistema DEBE transicionar automáticamente el estado de la OT (`ESTADOS_OT`: en revisión,
  pendiente, en curso, finalizada, entregada) en función del avance agregado de sus tareas, sin permitir
  edición manual directa del campo estado salvo corrección administrativa auditada.
- **FR-005**: El sistema DEBE registrar fecha de inicio y fecha de finalización real de cada OT/tarea y
  calcular el comparativo contra el tiempo estimado.
- **FR-006**: El sistema DEBE permitir cargar evidencias (imágenes/documentos) asociadas a una OT en
  cualquier etapa del proceso.
- **FR-007**: El sistema DEBE exigir la validación de un checklist de cierre (`CHECKLIST_OT`) antes de
  permitir marcar una OT como Finalizada.
- **FR-008**: El sistema DEBE implementar el flujo de entrega: solicitud de salida del equipo → aprobación
  administrativa → confirmación de entrega, transicionando la OT a "Entregada" solo tras confirmación.
- **FR-009**: El sistema DEBE permitir al Administrador y al Jefe de Taller corregir una OT en curso
  (tareas, responsables, insumos) preservando el historial de cambios y sin perder evidencias/tareas ya
  completadas.
- **FR-010**: El sistema DEBE generar una alerta cuando una OT esté próxima a vencer o vencida respecto a
  su tiempo estimado [NEEDS CLARIFICATION: umbral de "próxima a vencer"].
- **FR-011**: El sistema DEBE notificar automáticamente al cliente por correo en los hitos relevantes del
  flujo (creación, entrega) — detalle del mecanismo en spec 008.
- **FR-012**: El sistema DEBE permitir consultar y filtrar el tablero de OT por estado, cliente, tipo de
  servicio, fechas y responsable (Ilustración 16).

### Key Entities

- **Orden de Trabajo** (`ORDENES_TRABAJO`): id, numero_ot, cliente_id, equipo_id, prioridad_id, tecnico_id,
  estado_id, fecha_creacion, fecha_finalizacion, observaciones.
- **Detalle de OT** (`DETALLE_OT`): tareas/ítems de la OT — descripción, cantidad, costo_unitario,
  valor_total (según ER; el mockup describe tareas con operario e insumo asociados).
- **Evidencia de OT** (`EVIDENCIAS_OT`): tipo_archivo, url_archivo, descripción, fecha_subida.
- **Checklist de OT** (`CHECKLIST_OT`): ítem, cumple (SI/NO), observaciones.
- **Estado de OT** (`ESTADOS_OT`): catálogo de estados, con indicador `es_terminal`.
- **Prioridad** (`PRIORIDADES`): catálogo de niveles de prioridad.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de las OT creadas cuentan con al menos una tarea y un técnico asignado (regla
  aplicada al 100% de los registros, no solo recomendada en UI).
- **SC-002**: El estado de una OT refleja el avance real de sus tareas sin intervención manual en al menos
  el 95% de los casos (excepto correcciones administrativas auditadas).
- **SC-003**: Ninguna OT puede marcarse como "Finalizada" con el checklist de cierre incompleto (0
  excepciones).
- **SC-004**: El Jefe de Taller puede filtrar y encontrar cualquier OT específica del tablero en menos de
  10 segundos usando los filtros disponibles.

## Assumptions

- Cada OT pertenece a un único cliente y, opcionalmente, a un único equipo.
- Un técnico puede tener múltiples tareas activas simultáneas en distintas OT (no hay restricción de
  exclusividad en el alcance descrito).
- El envío de notificación automática al cliente reutiliza la infraestructura de correo definida en la
  spec 006/008, no una integración independiente.
