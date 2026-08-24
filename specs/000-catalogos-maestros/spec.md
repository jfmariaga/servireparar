# Feature Specification: Catálogos Maestros (Clientes, Proveedores, Contratistas)

**Feature Branch**: `000-catalogos-maestros`

**Created**: 2026-08-24

**Status**: Draft

**Input**: Formato real de Orden de Trabajo (`Ordenes de Trabajo Servireparar.xlsx - DESMANTELAR ESCALERA`)
y Proceso de Gestión de OT (documento operativo real, Cartagena 2026-09-02) provistos por el cliente, que
evidencian que la creación de una OT depende de catálogos base (Cliente, Contratista) que deben existir
antes de que los módulos operativos (002 OT, 003 Inventario, 006 Compras/Cotizaciones) puedan funcionar.
Complementa (no reemplaza) las entidades `CLIENTES` y `PROVEEDORES` originalmente descritas en los specs
005 y 006.

## Contexto y motivación

Los specs 002 (OT), 003 (Inventario) y 006 (Compras/Cotizaciones) asumen que ya existen registros de
Cliente, Proveedor y (nuevo) Contratista al momento de operar. Sin este módulo previo, no es posible crear
una Orden de Trabajo real (falta el cliente), registrar una entrada de inventario con proveedor, ni asignar
mano de obra externa a una OT. Este spec formaliza esos catálogos como una capa de datos base, priorizada
al inicio de la Fase 2 del cronograma (antes o en paralelo a la primera OT real), de forma que 002/003/006
puedan referenciarlos en lugar de redefinirlos.

Nota de alcance: el catálogo de Técnicos (operarios propios con su especialidad) es igualmente un
prerrequisito para crear una OT ("si no tengo el operario con su especialidad no puedo crear una orden"),
pero ya está formalizado en el [spec 004](../004-gestion-personal/spec.md) — no se duplica aquí.

## Clarifications

### Session 2026-08-24

- Q: ¿Los contratistas (mano de obra externa, ej. "SOLUCIONES BALLESTAS") deben modelarse como una entidad
  propia, distinta de Proveedores? → A: Sí, entidad propia `CONTRATISTAS`, separada de `PROVEEDORES` (que
  venden repuestos/insumos) y de `TECNICOS` (personal propio) — refleja el formato real de OT, que separa
  "Mano de Obra Servireparar" de "Mano de Obra Contratista" y de "Repuestos/Insumos".

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Registrar un cliente antes de poder crear una OT (Priority: P1)

Antes de poder crear cualquier Orden de Trabajo, debe existir el registro del cliente (persona o empresa)
que solicita el servicio.

**Why this priority**: Es un bloqueante absoluto — el spec 002 (OT) exige `cliente_id` como campo
obligatorio desde su primera historia de usuario; sin cliente registrado, la creación de OT falla.

**Independent Test**: Se registra un cliente nuevo y se verifica que queda inmediatamente disponible en el
selector de cliente al crear una OT (spec 002) o una Cotización (spec 006).

**Acceptance Scenarios**:

1. **Given** un usuario con permisos (Administrador, Jefe de Taller, o quien reciba el equipo), **When**
   registra un cliente con nombre/razón social, NIT (si es empresa), teléfono, correo y dirección, **Then**
   el cliente queda disponible de inmediato para asociarlo a una OT, Cotización o Equipo.
2. **Given** un cliente ya registrado, **When** se intenta registrar otro con el mismo NIT, **Then** el
   sistema advierte la duplicidad y sugiere usar el registro existente.

---

### User Story 2 - Registrar un proveedor antes de poder recibir inventario (Priority: P1)

Antes de registrar una entrada de inventario o una solicitud de compra, debe existir el registro del
proveedor que suministra los repuestos/insumos.

**Why this priority**: El spec 003 (Inventario) y el spec 006 (Compras) requieren `proveedor_id` para
registrar movimientos de entrada y solicitudes de compra con trazabilidad de costos.

**Independent Test**: Se registra un proveedor nuevo y se verifica que aparece disponible al registrar una
entrada de inventario o una solicitud de compra.

**Acceptance Scenarios**:

1. **Given** un usuario con permisos, **When** registra un proveedor con nombre, NIT, correo, dirección y
   estado, **Then** el proveedor queda disponible en los selectores de compras y entradas de inventario.

---

### User Story 3 - Registrar un contratista antes de poder asignarlo a una OT (Priority: P1)

Antes de poder registrar mano de obra externa en una OT, debe existir el registro del contratista
(persona o empresa) que presta ese servicio.

**Why this priority**: Confirmado en Clarifications — es una entidad nueva, evidenciada por el formato real
de OT ("Mano de Obra Contratista": nombre/empresa, especialidad, cantidad, valor), distinta de Proveedores
y necesaria para el costeo de OT (ver spec 002, User Story 5).

**Independent Test**: Se registra un contratista nuevo y se verifica que aparece disponible al agregar mano
de obra externa a una tarea de OT.

**Acceptance Scenarios**:

1. **Given** un usuario con permisos, **When** registra un contratista con nombre/empresa, especialidad y
   datos de contacto, **Then** el contratista queda disponible para asociarlo a tareas de OT como mano de
   obra externa.
2. **Given** un contratista registrado, **When** se le asocia una tarifa/valor por servicio en una OT
   (spec 002), **Then** ese valor se usa para el cálculo de costo total de la OT.

### Edge Cases

- ¿Qué pasa si un cliente, proveedor o contratista se marca como inactivo pero tiene OT/compras/
  movimientos históricos asociados? El registro histórico se conserva; solo deja de estar disponible para
  nuevas asignaciones.
- ¿Puede una misma persona/empresa ser simultáneamente Cliente y Proveedor o Contratista (ej. un taller
  aliado que a veces compra servicios y a veces los presta)? Se permite — son catálogos independientes sin
  restricción de exclusividad; no hay deduplicación automática entre ellos.
- Identificación de duplicados: matcheo por NIT/correo al registrar, con advertencia no bloqueante (el
  usuario puede continuar si confirma que es un registro distinto, ej. dos sucursales del mismo cliente).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE permitir registrar, editar y activar/inactivar Clientes (nombre/razón social,
  NIT, teléfono, correo, dirección).
- **FR-002**: El sistema DEBE permitir registrar, editar y activar/inactivar Proveedores (nombre, NIT,
  correo, dirección, estado).
- **FR-003**: El sistema DEBE permitir registrar, editar y activar/inactivar Contratistas (nombre/empresa,
  especialidad, teléfono, correo), como entidad independiente de Proveedores y Técnicos.
- **FR-004**: El sistema DEBE advertir (sin bloquear) cuando se intente registrar un Cliente, Proveedor o
  Contratista con un NIT o correo ya existente en el mismo catálogo.
- **FR-005**: Solo los registros activos de Cliente, Proveedor y Contratista DEBEN aparecer en los
  selectores de creación de OT, Cotización, entrada de inventario o solicitud de compra; los inactivos se
  conservan para consulta histórica.
- **FR-006**: Estos tres catálogos DEBEN estar disponibles y operativos antes de que cualquier OT (spec
  002), movimiento de inventario con proveedor (spec 003) o cotización (spec 006) pueda registrarse — son
  un prerrequisito de datos, no un módulo con flujo propio de estados.

### Key Entities

- **Cliente**: id, nombre/razón social, nit, teléfono, correo, dirección, estado. Consumido por spec 002
  (OT), spec 005 (Equipos), spec 006 (Cotizaciones).
- **Proveedor**: id, nombre, nit, correo, dirección, estado. Consumido por spec 003 (Inventario — entradas)
  y spec 006 (Compras).
- **Contratista**: id, nombre/empresa, especialidad, teléfono, correo, estado. Consumido por spec 002 (OT —
  mano de obra externa y costeo).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Es posible registrar un Cliente, Proveedor y Contratista nuevos, cada uno en menos de 1
  minuto, sin necesidad de otros módulos previamente configurados.
- **SC-002**: El 100% de las OT, movimientos de inventario con proveedor y cotizaciones creadas referencian
  un registro válido y activo de estos catálogos (no texto libre).
- **SC-003**: Ningún registro inactivo de Cliente/Proveedor/Contratista aparece en los selectores de
  creación de nuevos registros operativos, sin afectar la consulta de su historial.

## Assumptions

- Este módulo no tiene flujo de aprobación ni estados de proceso propio (a diferencia de OT o Cotizaciones)
  — es CRUD de datos maestros con activo/inactivo.
- La gestión de estos tres catálogos está disponible para los roles Administrador y Jefe de Taller (quien
  suele recibir el equipo y abrir la OT, según el proceso real documentado); el detalle de permisos por rol
  se define en `/speckit-plan`.
- Un Contratista puede facturar por tarifa fija, por hora o por cantidad de servicio (ver formato real de
  OT: "Cantidad" y "Valor"); el modelo de tarificación exacto se define en `/speckit-plan` del spec 002,
  que es quien consume el costo.
