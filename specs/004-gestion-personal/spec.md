# Feature Specification: Gestión de Personal

**Feature Branch**: `004-gestion-personal`

**Created**: 2026-08-24

**Status**: Draft

**Input**: Cotización SERVIREPARAR, Módulo 3 — Gestión de Personal; Fase 2 del cronograma; ER: `TECNICOS`,
`USUARIOS`, `ROLES`, `ORDENES_TRABAJO`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Registrar y configurar trabajadores (Priority: P1)

El Administrador registra trabajadores (técnicos) del taller, asociándolos a un usuario del sistema y
definiendo su especialidad y estado de actividad.

**Why this priority**: Prerrequisito para que existan técnicos asignables a tareas de OT (spec 002); sin
esto el módulo central de la operación no puede funcionar con datos reales.

**Independent Test**: Se crea un usuario con rol Técnico y su registro correspondiente en `TECNICOS` con
especialidad, y se verifica que aparece disponible como operario al crear una tarea de OT.

**Acceptance Scenarios**:

1. **Given** el Administrador autenticado, **When** registra un trabajador con especialidad y lo marca
   activo, **Then** el trabajador queda disponible en los selectores de "operario" al crear tareas de OT.
2. **Given** un trabajador marcado como inactivo, **When** se intenta asignarlo a una nueva tarea de OT,
   **Then** el sistema no lo ofrece como opción disponible.

---

### User Story 2 - Asignación de trabajadores a Órdenes de Trabajo (Priority: P1)

El sistema permite ver, para cada técnico, su carga de trabajo actual (tareas/OT activas) para apoyar la
distribución de carga laboral al momento de asignar nuevas tareas.

**Why this priority**: La cotización pide explícitamente "distribución de carga laboral" como parte de la
asignación de recursos del Módulo 1, y es información que vive naturalmente en Gestión de Personal.

**Independent Test**: Con dos técnicos, uno con 3 tareas activas y otro con 0, se verifica que el Jefe de
Taller puede ver esa carga al momento de asignar una nueva tarea.

**Acceptance Scenarios**:

1. **Given** varios técnicos activos, **When** el Jefe de Taller abre el selector de operario en una tarea
   de OT, **Then** puede ver o inferir la carga actual de cada técnico (cantidad de tareas activas).
2. **Given** un técnico con historial de tareas, **When** se consulta su ficha, **Then** se listan las OT en
   las que ha participado.

---

### User Story 3 - Medir desempeño del personal (Priority: P2)

El Administrador consulta indicadores de desempeño por técnico: tiempos de ejecución, participación en OT
y productividad.

**Why this priority**: Explícitamente listado en la cotización como parte del Módulo 3; apoya decisiones de
gestión de personal pero no bloquea la operación diaria del taller.

**Independent Test**: Con historial de OT finalizadas por un técnico, se genera su reporte de desempeño y
se verifica que refleja tiempos reales vs. estimados y cantidad de OT en las que participó.

**Acceptance Scenarios**:

1. **Given** un técnico con tareas finalizadas en un rango de fechas, **When** el Administrador consulta su
   desempeño, **Then** el sistema muestra tiempo promedio de ejecución, número de OT/tareas y comparativo
   estimado vs. real.
2. **Given** varios técnicos, **When** el Administrador compara su productividad, **Then** puede ordenar o
   filtrar por el indicador de interés.

### Edge Cases

- ¿Qué pasa con las métricas de desempeño de un técnico que es inactivado? Deben conservarse históricamente
  aunque el técnico ya no esté activo.
- Un usuario puede tener rol Técnico y simultáneamente otro rol (ej. Jefe de Taller que también ejecuta
  tareas): la ficha de `TECNICOS` debe poder coexistir con múltiples roles del mismo usuario (ver spec 001).
- ¿Se requiere un catálogo de especialidades predefinido o es texto libre? [NEEDS CLARIFICATION: la
  cotización no detalla si "especialidad" es un catálogo fijo (ej. eléctrico, mecánico, refrigeración) o
  campo libre].

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE permitir registrar un trabajador vinculado a un usuario existente, con
  especialidad y estado activo/inactivo (`TECNICOS`).
- **FR-002**: El sistema DEBE ofrecer únicamente técnicos activos como opciones de asignación de tareas en
  el módulo de OT.
- **FR-003**: El sistema DEBE calcular y exponer, por técnico, tiempos de ejecución (real vs. estimado),
  cantidad de OT/tareas en las que participó y una métrica de productividad.
- **FR-004**: El sistema DEBE permitir consultar el historial completo de participación de un técnico en
  Órdenes de Trabajo.
- **FR-005**: El sistema DEBE exponer, al momento de asignar una tarea, la carga actual (tareas activas) de
  cada técnico disponible.
- **FR-006**: El sistema DEBE conservar las métricas históricas de un técnico aunque posteriormente sea
  inactivado.

### Key Entities

- **Técnico** (`TECNICOS`): id, usuario_id (FK a `USUARIOS`), especialidad, activo.
- Relación indirecta con **Orden de Trabajo** / **Detalle de OT** (spec 002) para el cálculo de desempeño.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El Administrador puede generar el reporte de desempeño de cualquier técnico activo en menos
  de 10 segundos.
- **SC-002**: El 100% de los técnicos inactivos quedan excluidos de los selectores de asignación de nuevas
  tareas.
- **SC-003**: Las métricas de desempeño reflejan con exactitud el historial de OT (validable contra el
  conteo manual de OT finalizadas por técnico en un período de prueba).

## Assumptions

- Un "trabajador"/técnico siempre corresponde 1:1 a un `USUARIO` con rol Técnico asignado (no se manejan
  técnicos externos sin cuenta en el sistema).
- Las métricas de productividad se calculan sobre datos de OT ya existentes en el sistema (no se requiere
  integración con nómina o control de asistencia).
