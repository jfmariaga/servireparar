# Feature Specification: Gestión de Inventario (Bodega)

**Feature Branch**: `003-inventario-bodega`

**Created**: 2026-08-24

**Status**: Draft

**Input**: Cotización SERVIREPARAR, Módulo 2 — Gestión de Inventario (Bodega); Fase 2 del cronograma;
Mockups Ilustraciones 17-18 (Flujo #3, Almacenista); ER: `INVENTARIO`, `MOVIMIENTOS_INVENTARIO`.

## Clarifications

### Session 2026-08-24

- Q: ¿Con qué periodicidad y quién es responsable de las auditorías de conteo físico vs. sistema? → A: Sin
  periodicidad fija; se inician bajo demanda, a criterio operativo del taller, sin recordatorio automático
  obligatorio.
- Q: Cuando el conteo físico difiere del stock en sistema, ¿quién puede aprobar el ajuste? → A: Requiere
  aprobación del Administrador; el Almacenista registra el conteo pero el ajuste queda pendiente hasta que
  el Administrador lo apruebe explícitamente (doble validación).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Atender solicitudes de insumos generadas desde una OT (Priority: P1)

El Almacenista revisa las solicitudes de insumos generadas automáticamente por las tareas de una OT
(spec 002), las aprueba y registra la entrega física al técnico responsable.

**Why this priority**: Es el punto de integración crítico entre Bodega y el módulo central de OT; sin esto
las tareas de OT no pueden ejecutarse con los insumos correctos.

**Independent Test**: Con una solicitud de insumo pendiente proveniente de una OT, el Almacenista la
aprueba y marca como entregada, y se verifica el descuento de stock correspondiente.

**Acceptance Scenarios**:

1. **Given** una solicitud de insumo pendiente originada por una tarea de OT, **When** el Almacenista la
   aprueba, **Then** la solicitud cambia a estado "Aprobada" y queda disponible para entrega
   (Ilustración 17).
2. **Given** una solicitud aprobada, **When** el Almacenista marca "Entregar", **Then** el sistema registra
   el movimiento de salida, descuenta stock si es consumible, y marca la herramienta como "En uso" si
   aplica.
3. **Given** una solicitud de insumo cuyo stock disponible es insuficiente, **When** el Almacenista intenta
   aprobarla/entregarla, **Then** el sistema bloquea o advierte la insuficiencia de stock.

---

### User Story 2 - Registrar solicitud manual de insumos sin OT (Priority: P1)

El Almacenista registra una salida directa de insumos hacia un cliente externo, sin que exista una Orden de
Trabajo asociada, manteniendo su propia trazabilidad de costos.

**Why this priority**: Es la "funcionalidad diferencial" explícita de la cotización (consumo de repuestos
para externos sin OT); representa un canal de ingreso/control de costos independiente.

**Independent Test**: Se registra una solicitud manual seleccionando cliente, insumo y cantidad, y se
verifica que se descuenta del stock y queda trazada como "Manual", separada de las solicitudes vía OT.

**Acceptance Scenarios**:

1. **Given** el Almacenista autenticado, **When** completa el formulario de "Solicitud de Insumo" manual
   con cliente, insumo y cantidad (Ilustración 18), **Then** el sistema registra la salida directa, sin
   requerir una OT asociada.
2. **Given** una solicitud manual registrada, **When** se consulta el historial de movimientos, **Then**
   queda claramente distinguible de las solicitudes originadas desde OT, con su costo asociado.

---

### User Story 3 - Devolución y control de estado de herramientas (Priority: P2)

Al finalizar el uso, el Almacenista registra la devolución de herramientas, evaluando su estado
(disponible, dañada, requiere mantenimiento).

**Why this priority**: Cierra el ciclo de vida de las herramientas reutilizables, evitando pérdida de
trazabilidad de activos de la empresa.

**Independent Test**: Se marca una herramienta "En uso" como devuelta, seleccionando su nuevo estado, y se
verifica que vuelve a estar disponible (o pasa a "Dañada"/"En mantenimiento" según corresponda).

**Acceptance Scenarios**:

1. **Given** una herramienta en estado "En uso" asociada a una OT finalizada, **When** el Almacenista
   registra su devolución en buen estado, **Then** la herramienta pasa a "Disponible" y queda libre para
   nueva asignación.
2. **Given** una herramienta devuelta en mal estado, **When** el Almacenista la marca como "Dañada" o "En
   mantenimiento", **Then** la herramienta queda excluida de nuevas asignaciones hasta cambiar de estado
   manualmente.

---

### User Story 4 - Alertas de stock mínimo y auditoría de inventario (Priority: P2)

El sistema alerta automáticamente cuando un consumible llega a su stock mínimo, y permite registrar
auditorías periódicas de conteo físico vs. sistema con ajustes controlados.

**Why this priority**: Previene desabastecimiento operativo y sostiene la confiabilidad del inventario en
el tiempo — explícitamente descrito como "Auditoría" en la cotización.

**Independent Test**: Se reduce el stock de un consumible por debajo de su `stock_minimo` mediante un
movimiento de salida y se verifica que se genera la alerta correspondiente (ver spec 008).

**Acceptance Scenarios**:

1. **Given** un consumible cuyo `stock_actual` cae por debajo de `stock_minimo` tras un movimiento de
   salida, **When** se guarda el movimiento, **Then** el sistema dispara una alerta de stock bajo.
2. **Given** un proceso de auditoría iniciado, **When** el Almacenista registra el conteo físico y difiere
   del stock en sistema, **Then** el sistema registra el ajuste propuesto en estado "Pendiente de
   aprobación" con el motivo, sin modificar aún el `stock_actual`.
3. **Given** un ajuste de auditoría pendiente, **When** el Administrador lo aprueba, **Then** el sistema
   aplica el ajuste al `stock_actual` y lo deja en el historial de la auditoría; si lo rechaza, el stock no
   se modifica y queda registrado el motivo del rechazo.

### Edge Cases

- ¿Qué ocurre si dos solicitudes concurrentes intentan reservar el mismo stock limitado de un consumible?
  Debe evitarse sobregiro de stock (descuentos atómicos).
- ¿Puede un ítem del catálogo cambiar de categoría (herramienta ↔ consumible) una vez tiene movimientos
  históricos? Debería restringirse o requerir justificación.
- Periodicidad y responsable de las auditorías de conteo físico: sin periodicidad fija; el Almacenista las
  inicia bajo demanda (ver Clarifications).
- Aprobación de ajustes de auditoría: requiere doble validación — el Almacenista registra el conteo, el
  Administrador aprueba antes de que impacte el `stock_actual` (ver Clarifications).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE clasificar cada ítem del inventario como Herramienta (reutilizable) o
  Consumible (uso único), según `INVENTARIO.tipo`.
- **FR-002**: El sistema DEBE gestionar el ciclo de estado de las herramientas (Disponible, En uso, Dañada,
  En mantenimiento) y su historial de uso.
- **FR-003**: El sistema DEBE descontar automáticamente el stock de consumibles al registrar una salida
  (vía OT o manual).
- **FR-004**: El sistema DEBE generar automáticamente solicitudes de insumo en Bodega a partir de las
  tareas de una OT que requieran herramientas/consumibles (integración con spec 002).
- **FR-005**: El sistema DEBE permitir registrar solicitudes manuales de salida de insumos hacia clientes
  externos, sin OT asociada, con su propia trazabilidad de costos.
- **FR-006**: El sistema DEBE registrar todo movimiento de inventario (`MOVIMIENTOS_INVENTARIO`: entrada,
  salida, devolución) con tipo, cantidad, fecha, motivo/referencia y usuario responsable.
- **FR-007**: El sistema DEBE generar una alerta cuando el `stock_actual` de un consumible caiga por debajo
  de su `stock_minimo`.
- **FR-008**: El sistema DEBE permitir al Almacenista iniciar un proceso de auditoría (conteo físico vs.
  sistema) en cualquier momento, sin periodicidad obligatoria, registrando los ajustes propuestos con
  motivo.
- **FR-008a**: Todo ajuste propuesto en una auditoría DEBE quedar en estado "Pendiente de aprobación" y
  requerir aprobación explícita del Administrador antes de modificar el `stock_actual`; el sistema DEBE
  conservar el historial completo (propuesto, aprobado/rechazado, por quién y cuándo).
- **FR-009**: El sistema DEBE impedir que una solicitud de insumo se apruebe/entregue si el stock
  disponible es insuficiente.
- **FR-010**: El sistema DEBE mantener asignación y responsable de cada herramienta prestada mientras esté
  en estado "En uso".

### Key Entities

- **Inventario** (`INVENTARIO`): id, código, nombre, tipo (Herramienta/Consumible), categoria_id,
  stock_actual, stock_minimo, unidad_medida, activo.
- **Movimiento de Inventario** (`MOVIMIENTOS_INVENTARIO`): id, inventario_id, tipo_mov (Entrada/Salida/
  Devolución), cantidad, fecha, motivo, referencia, usuario_id.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de las salidas de inventario (vía OT o manuales) quedan registradas con responsable y
  fecha, sin excepciones.
- **SC-002**: El sistema nunca permite que el `stock_actual` de un consumible quede negativo.
- **SC-003**: Toda alerta de stock mínimo se genera en el mismo movimiento que la origina (latencia
  percibida < 1 minuto).
- **SC-004**: El 100% de las devoluciones de herramientas quedan con un estado final explícito (Disponible,
  Dañada o En mantenimiento), sin quedar en estado ambiguo "En uso" tras la devolución.

## Assumptions

- Existe un catálogo de categorías de inventario (`categoria_id`) mantenido por el Administrador/
  Almacenista, aunque no está detallado como módulo propio en la cotización.
- El costo unitario de los insumos usados en solicitudes manuales sin OT se toma del maestro de inventario
  vigente al momento de la salida.
- Las auditorías son un proceso manual asistido por el sistema (no hay integración con hardware de conteo
  como lectores de código de barras en el alcance actual).
