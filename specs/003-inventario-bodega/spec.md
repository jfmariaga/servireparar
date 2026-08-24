# Feature Specification: Gestión de Inventario (Bodega)

**Feature Branch**: `003-inventario-bodega`

**Created**: 2026-08-24

**Status**: Draft

**Input**: Cotización SERVIREPARAR, Módulo 2 — Gestión de Inventario (Bodega); Fase 2 del cronograma;
Mockups Ilustraciones 17-18 (Flujo #3, Almacenista); ER: `INVENTARIO`, `MOVIMIENTOS_INVENTARIO`.
Complementado con el "Manual de Organización y Mapa de Procesos — Almacén Taller SERVIREPARAR" (Cartagena,
2026-09-25) y el Excel real de inventario (`INVENTARIO SERVIREPARAR.xlsx`, hojas: Llantas, EPP, Tuberías y
Láminas, Insumos, Precios, Pinturas, Herramientas, Repuestos), documentos operativos reales provistos por
el cliente.

## Clarifications

### Session 2026-08-24

- Q: ¿Con qué periodicidad y quién es responsable de las auditorías de conteo físico vs. sistema? → A: Sin
  periodicidad fija; se inician bajo demanda, a criterio operativo del taller, sin recordatorio automático
  obligatorio.
- Q: Cuando el conteo físico difiere del stock en sistema, ¿quién puede aprobar el ajuste? → A: Requiere
  aprobación del Administrador; el Almacenista registra el conteo pero el ajuste queda pendiente hasta que
  el Administrador lo apruebe explícitamente (doble validación).

### Session 2026-08-24 (validación contra documentos operativos reales)

- Q: El manual de almacén recomienda auditoría mensual; ¿se corrige la periodicidad "bajo demanda" ya
  decidida? → A: No — se mantiene "bajo demanda, sin periodicidad fija"; la recomendación mensual del
  manual se toma como buena práctica operativa, no como requisito estricto del sistema.
- Q: El Excel real de inventario usa 7 categorías (Llantas, EPP, Tuberías y Láminas, Insumos, Pinturas,
  Herramientas, Repuestos) en vez del tipo binario Herramienta/Consumible. ¿Se adoptan como base del
  catálogo de categorías? → A: Sí — el catálogo de categorías se siembra con estas 7 categorías reales,
  manteniendo Herramienta/Consumible como un atributo aparte (reutilizable o no) dentro de cada categoría.
- Q: El manual pide códigos de barra Code128 por ítem para agilizar entradas/salidas. ¿Se incluye en el
  alcance? → A: Sí — el sistema genera e imprime el código (basado en el código interno del ítem) y admite
  lectura vía lector USB tipo teclado (sin hardware/SDK especial).

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

---

### User Story 5 - Codificación, ubicación física y código de barras (Priority: P2)

Cada ítem de inventario tiene una categoría (del catálogo real: Llantas, EPP, Tuberías y Láminas, Insumos,
Pinturas, Herramientas, Repuestos), un código interno con prefijo por tipo, una ubicación física
(Pasillo-Estante-Nivel) y un código de barras generado por el sistema para agilizar sus movimientos.

**Why this priority**: Es el esquema de organización física y de codificación que el almacén ya usa en la
práctica (Manual de Almacén); digitalizarlo reduce tiempos de búsqueda y errores de digitación en entradas/
salidas.

**Independent Test**: Se registra un ítem nuevo de categoría "Repuestos", se le asigna ubicación
`A-01-01`, se verifica que el sistema genera su código interno con prefijo `REP-` y un código de barras
imprimible, y que ese código puede usarse para buscar el ítem mediante un lector USB.

**Acceptance Scenarios**:

1. **Given** un ítem nuevo, **When** se registra seleccionando su categoría del catálogo (Llantas, EPP,
   Tuberías y Láminas, Insumos, Pinturas, Herramientas, Repuestos), **Then** el sistema asigna
   automáticamente un código interno con el prefijo correspondiente al tipo (`REP-`, `HER-`, `CON-`,
   `ACC-`) y un consecutivo único.
2. **Given** un ítem con código interno asignado, **When** se registra su ubicación física, **Then** el
   sistema valida el formato `PASILLO-ESTANTE-NIVEL` (ej. `A-01-01`).
3. **Given** un ítem registrado, **When** el Almacenista solicita su etiqueta, **Then** el sistema genera un
   código de barras Code128 imprimible basado en su código interno.
4. **Given** un lector de código de barras USB (modo teclado) conectado, **When** se escanea el código de
   un ítem en un formulario de movimiento, **Then** el sistema identifica el ítem automáticamente sin
   necesidad de búsqueda manual.

### Edge Cases

- ¿Qué ocurre si dos solicitudes concurrentes intentan reservar el mismo stock limitado de un consumible?
  Debe evitarse sobregiro de stock (descuentos atómicos).
- ¿Puede un ítem del catálogo cambiar de categoría (herramienta ↔ consumible) una vez tiene movimientos
  históricos? Debería restringirse o requerir justificación.
- Periodicidad y responsable de las auditorías de conteo físico: sin periodicidad fija; el Almacenista las
  inicia bajo demanda (ver Clarifications).
- Aprobación de ajustes de auditoría: requiere doble validación — el Almacenista registra el conteo, el
  Administrador aprueba antes de que impacte el `stock_actual` (ver Clarifications).
- ¿Qué pasa si dos ítems distintos quedan asignados a la misma ubicación física por error? El sistema debe
  advertir la colisión de ubicación al momento de asignarla, sin bloquear (puede haber más de un ítem por
  nivel/estante en la práctica).
- Categorías y prefijos de código no son exhaustivos del Excel real (ej. "Precios" y "Pinturas" no tienen
  prefijo propio documentado) — se normalizan en `/speckit-plan` mapeando cada categoría real a uno de los
  4 prefijos base (`REP-`, `HER-`, `CON-`, `ACC-`) según corresponda.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE clasificar cada ítem del inventario en una categoría de un catálogo
  configurable, sembrado inicialmente con las categorías reales del taller (Llantas, EPP, Tuberías y
  Láminas, Insumos, Pinturas, Herramientas, Repuestos), manteniendo además el atributo Herramienta
  (reutilizable) / Consumible (uso único) independiente de la categoría.
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
- **FR-011**: El sistema DEBE asignar automáticamente a cada ítem nuevo un código interno con prefijo por
  tipo (`REP-`, `HER-`, `CON-`, `ACC-`) y un consecutivo único.
- **FR-012**: El sistema DEBE permitir registrar la ubicación física de cada ítem en formato
  `PASILLO-ESTANTE-NIVEL` (ej. `A-01-01`), advirtiendo (sin bloquear) si dos ítems distintos comparten
  ubicación.
- **FR-013**: El sistema DEBE generar e imprimir un código de barras Code128 por ítem, basado en su código
  interno, y DEBE permitir identificar un ítem en formularios de movimiento mediante lectura de ese código
  con un lector USB en modo teclado (sin requerir hardware o SDK especial).
- **FR-014**: Los movimientos de entrada de inventario DEBEN registrar el Proveedor (spec 000) que
  suministró el ítem.

### Key Entities

- **Inventario** (`INVENTARIO`): id, código (con prefijo por tipo), nombre, tipo (Herramienta/Consumible),
  categoria_id, ubicacion (Pasillo-Estante-Nivel), codigo_barras, stock_actual, stock_minimo,
  unidad_medida, activo.
- **Categoría de Inventario**: catálogo configurable — Llantas, EPP, Tuberías y Láminas, Insumos, Pinturas,
  Herramientas, Repuestos (semilla inicial basada en el Excel real).
- **Movimiento de Inventario** (`MOVIMIENTOS_INVENTARIO`): id, inventario_id, tipo_mov (Entrada/Salida/
  Devolución), cantidad, fecha, motivo, referencia, usuario_id, proveedor_id (spec 000, en entradas).

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

- El catálogo de categorías de inventario (`categoria_id`) es mantenido por el Administrador/Almacenista y
  se siembra inicialmente con las 7 categorías reales del taller (ver Clarifications).
- El costo unitario de los insumos usados en solicitudes manuales sin OT se toma del maestro de inventario
  vigente al momento de la salida.
- Las auditorías son un proceso manual asistido por el sistema; la lectura de código de barras (User Story
  5) agiliza el registro de movimientos y conteos, pero no reemplaza el conteo físico humano.
- Los lectores de código de barras se asumen tipo "USB HID" (actúan como teclado), sin necesidad de drivers
  ni SDK propietario — compatible con cualquier formulario web estándar.
