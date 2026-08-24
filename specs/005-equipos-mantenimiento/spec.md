# Feature Specification: Gestión de Equipos y Mantenimiento

**Feature Branch**: `005-equipos-mantenimiento`

**Created**: 2026-08-24

**Status**: Draft

**Input**: Cotización SERVIREPARAR, Módulo 4 — Gestión de Equipos y Mantenimiento; Fase 3 del cronograma;
ER: `CLIENTES`, `EQUIPOS`, `ORDENES_TRABAJO`, `EVIDENCIAS_OT`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Registrar clientes y sus equipos (Priority: P1)

Se registran clientes y los equipos que poseen (tipo, marca, modelo, número de serie, ubicación, estado),
para poder asociarlos posteriormente a Órdenes de Trabajo.

**Why this priority**: Es información base requerida por el módulo de OT (spec 002) desde su primera
historia de usuario ("registro de cliente/equipo"); sin esto no se puede operar el flujo central.

**Independent Test**: Se registra un cliente y un equipo asociado, y se verifica que ambos aparecen
disponibles como opciones al crear una nueva OT.

**Acceptance Scenarios**:

1. **Given** un usuario con permisos, **When** registra un nuevo cliente con sus datos de contacto,
   **Then** el cliente queda disponible para asociar equipos y Órdenes de Trabajo.
2. **Given** un cliente existente, **When** se registra un nuevo equipo con tipo, marca, modelo, serie y
   estado, **Then** el equipo queda disponible en el selector de equipos al crear una OT para ese cliente.

---

### User Story 2 - Consultar historial técnico de un equipo (Priority: P1)

Para cualquier equipo, se puede consultar el historial de mantenimientos realizados, técnico responsable,
variables técnicas registradas y evidencias asociadas.

**Why this priority**: Es el valor diferencial central del módulo — trazabilidad técnica completa por
equipo a lo largo del tiempo, explícito en la cotización.

**Independent Test**: Con un equipo que tiene 2 OT de mantenimiento finalizadas, se consulta su historial y
se verifica que ambas aparecen con técnico, fecha y evidencias.

**Acceptance Scenarios**:

1. **Given** un equipo con Órdenes de Trabajo de mantenimiento finalizadas, **When** se consulta su ficha,
   **Then** se listan cronológicamente los mantenimientos con técnico responsable y evidencias.
2. **Given** una OT de mantenimiento en curso para un equipo, **When** se registran variables técnicas
   (ej. temperatura, presión), **Then** quedan asociadas a esa intervención y visibles en el historial del
   equipo.

---

### User Story 3 - Programar mantenimiento preventivo (Priority: P2)

El sistema programa automáticamente próximos mantenimientos preventivos por equipo y genera alertas de
vencimiento antes de la fecha programada.

**Why this priority**: Explícitamente descrito como "Programación automática" y "Alertas de vencimiento" en
la cotización; da valor proactivo más allá del mantenimiento correctivo reactivo.

**Independent Test**: Se configura una periodicidad de mantenimiento preventivo para un equipo y se
verifica que, al acercarse la fecha calculada, se genera la alerta correspondiente.

**Acceptance Scenarios**:

1. **Given** un equipo con periodicidad de mantenimiento preventivo definida, **When** se completa un
   mantenimiento, **Then** el sistema calcula automáticamente la próxima fecha programada.
2. **Given** un mantenimiento preventivo próximo a vencer, **When** se acerca la fecha calculada, **Then**
   el sistema genera una alerta (ver spec 008) antes del vencimiento.

---

### User Story 4 - Checklist técnico digital (Priority: P2)

Durante una intervención de mantenimiento, el técnico completa un checklist técnico digital específico del
tipo de equipo/servicio.

**Why this priority**: Explícito en la cotización ("Checklist técnico digital"); estandariza la calidad de
las intervenciones técnicas, similar en mecánica al checklist de cierre de OT (spec 002) pero orientado a
variables técnicas del equipo.

**Independent Test**: Se completa un checklist técnico durante una OT de mantenimiento y se verifica que
queda asociado a esa intervención en el historial del equipo.

**Acceptance Scenarios**:

1. **Given** una OT de mantenimiento en curso, **When** el técnico completa el checklist técnico del
   equipo, **Then** el resultado queda almacenado y visible en el historial técnico del equipo.

### Edge Cases

- ¿Qué pasa si un equipo se traslada de ubicación o cambia de cliente propietario? Debe permitir
  actualización manteniendo el historial técnico previo intacto.
- ¿Puede un equipo estar asociado a más de un cliente a la vez (ej. arrendamiento)? El ER modela
  `cliente_id` como FK simple en `EQUIPOS`, sugiriendo un único propietario a la vez.
- Variables técnicas configurables vs. fijas: [NEEDS CLARIFICATION: el ER no modela columnas propias para
  "variables técnicas (temperatura, presión, etc.)"; se requiere definir si son campos fijos por tipo de
  equipo o un esquema configurable clave-valor].
- Checklist técnico digital: [NEEDS CLARIFICATION: ¿es una plantilla única global o varía según tipo de
  equipo/servicio? La cotización no lo especifica].

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE permitir registrar clientes con sus datos de contacto (nombre, teléfono,
  correo, dirección, NIT).
- **FR-002**: El sistema DEBE permitir registrar equipos asociados a un cliente, con tipo, marca, modelo,
  número de serie, ubicación y estado.
- **FR-003**: El sistema DEBE construir automáticamente el historial técnico de un equipo a partir de sus
  Órdenes de Trabajo de mantenimiento (integración con spec 002), incluyendo técnico responsable y
  evidencias.
- **FR-004**: El sistema DEBE permitir registrar variables técnicas asociadas a cada intervención de
  mantenimiento sobre un equipo.
- **FR-005**: El sistema DEBE programar automáticamente la próxima fecha de mantenimiento preventivo tras
  completar un mantenimiento, según la periodicidad configurada para el equipo.
- **FR-006**: El sistema DEBE generar una alerta cuando un mantenimiento preventivo esté próximo a vencer.
- **FR-007**: El sistema DEBE permitir completar un checklist técnico digital durante una intervención de
  mantenimiento, asociado al historial del equipo.

### Key Entities

- **Cliente** (`CLIENTES`): id, nombre, teléfono, correo, dirección, nit.
- **Equipo** (`EQUIPOS`): id, cliente_id, nombre, marca, modelo, serie, observaciones (ubicación y estado
  según cotización, a incorporar en el modelo final).
- Relación con **Orden de Trabajo** (spec 002) para historial técnico, evidencias y checklist.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El historial técnico de cualquier equipo con OT previas se reconstruye completamente desde el
  sistema, sin necesidad de consultar registros externos.
- **SC-002**: El 100% de los mantenimientos preventivos configurados generan su alerta de vencimiento antes
  de la fecha límite calculada.
- **SC-003**: Un usuario puede registrar un equipo nuevo y asociarlo a una OT en menos de 2 minutos.

## Assumptions

- El registro de clientes de este módulo es la misma entidad `CLIENTES` usada por el módulo de OT (spec
  002) y de Compras/Cotizaciones (spec 006) — no hay clientes duplicados por módulo.
- El mantenimiento preventivo aplica solo a equipos marcados explícitamente con periodicidad definida; no
  todos los equipos registrados requieren programación automática.
