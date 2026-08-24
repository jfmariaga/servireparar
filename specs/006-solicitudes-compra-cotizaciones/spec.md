# Feature Specification: Solicitudes de Compra y Gestión de Cotizaciones

**Feature Branch**: `006-solicitudes-compra-cotizaciones`

**Created**: 2026-08-24

**Status**: Draft

**Input**: Cotización SERVIREPARAR, Módulo 5 — Solicitudes de Compra; Fase 3 del cronograma; Mockups
Ilustraciones 5-11 (Flujo #1, Administrador); ER: `PROVEEDORES`, `COMPRAS`, `DETALLE_COMPRA`, `CLIENTES`,
`EQUIPOS`. El registro de Proveedores y Clientes se movió al
[spec 000 — Catálogos Maestros](../000-catalogos-maestros/spec.md), priorizado antes de este módulo por ser
prerrequisito de Inventario (spec 003) y OT (spec 002); este spec los consume por referencia.

## Clarifications

### Session 2026-08-24

- Q: ¿Qué mecanismo técnico se usará para recibir automáticamente las solicitudes de cotización que llegan
  al correo oficial? → A: IMAP polling — el sistema se conecta periódicamente vía IMAP a la cuenta oficial
  con un job programado, independiente del proveedor de correo (Gmail, Hostinger, Outlook).
- Q: El cliente responde una cotización fuera del hilo original (correo nuevo, no un "Responder"). ¿Qué
  debe hacer el sistema? → A: Genera una notificación interna al Administrador para revisión/vinculación
  manual, en vez de descartar el correo silenciosamente.
- Q: ¿Cómo se debe modelar el "Caso de Cotización" en la base de datos? → A: Entidad propia e independiente
  ("Cotizaciones" + detalle + mensajes del hilo), separada de `ORDENES_TRABAJO`, dado que su ciclo de vida
  (recepción por correo, envío, aceptación, facturación) es distinto al de una OT operativa.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Recepción y registro de solicitudes de cotización desde correo (Priority: P1)

Las solicitudes de cotización que llegan al correo oficial de la empresa se registran automáticamente en el
sistema como casos gestionables, visibles en el tablero del Administrador.

**Why this priority**: Es el punto de entrada de todo el flujo comercial descrito en el mockup (Flujo #1);
sin la recepción no hay caso que gestionar.

**Independent Test**: Se simula/envía un correo de solicitud de cotización a la cuenta configurada y se
verifica que aparece como nuevo caso "En revisión" en el tablero de cotizaciones del Administrador.

**Acceptance Scenarios**:

1. **Given** un correo oficial configurado en el sistema, **When** llega un nuevo correo de solicitud de
   cotización, **Then** el sistema crea automáticamente un caso de cotización en estado "En revisión",
   asociado al cliente remitente si es identificable (Ilustración 5, "Cotizaciones Activas").
2. **Given** un correo entrante que no sigue el formato de la plantilla esperada o no pertenece a ningún
   hilo/caso reconocido, **When** se recibe (vía IMAP polling), **Then** el sistema genera una notificación
   interna al Administrador para revisión y registro manual, sin descartar el correo silenciosamente.
3. **Given** un caso de cotización recibido, **When** el Administrador lo abre, **Then** puede ver la
   información general (cliente, equipo, tipo de servicio, adjunto original) según Ilustración 6.

---

### User Story 2 - Construir y enviar una cotización usando la maestra de servicios/insumos (Priority: P1)

El Administrador construye la cotización seleccionando servicios e insumos de un catálogo preconfigurado, y
la envía al cliente directamente desde la plataforma.

**Why this priority**: Es el valor agregado explícito del mockup ("el módulo no funcione únicamente como
lectura y respuesta de correos, sino como herramienta activa"); habilita cotizaciones consistentes y
trazables sin depender solo de redacción manual de correos.

**Independent Test**: Se construye una cotización agregando ítems de la maestra de servicios/insumos con
cantidades, se guarda como borrador, se reabre y se envía, verificando que el PDF/adjunto llega al cliente
y el estado cambia a "Cotizada".

**Acceptance Scenarios**:

1. **Given** un caso de cotización en revisión, **When** el Administrador agrega servicios e insumos desde
   la maestra con sus cantidades, **Then** el sistema calcula automáticamente el total (Ilustración 7-8).
2. **Given** una cotización en construcción, **When** el Administrador elige "Guardar" en lugar de
   "Enviar", **Then** la cotización queda almacenada como borrador editable, sin notificar al cliente.
3. **Given** una cotización completa, **When** el Administrador elige "Enviar Cotización", **Then** el
   sistema genera el documento de cotización, lo envía al correo del cliente y cambia el estado a
   "Cotizada", quedando registrado en el hilo de comunicación (Ilustración 9).

---

### User Story 3 - Seguimiento de respuesta del cliente y cierre del caso (Priority: P1)

El sistema captura automáticamente la respuesta del cliente (aceptación o rechazo) desde el mismo hilo de
correo, y permite avanzar el caso hasta entrega y facturación.

**Why this priority**: Cierra el ciclo comercial completo descrito en el Flujo #1 del mockup; es lo que da
valor de "trazabilidad de todo el proceso comercial" mencionado explícitamente.

**Independent Test**: Se simula la respuesta de aceptación del cliente en el mismo hilo y se verifica que
el estado del caso cambia a "Aceptada" automáticamente y sin edición manual del Administrador.

**Acceptance Scenarios**:

1. **Given** una cotización enviada y en espera de respuesta, **When** el cliente responde en el mismo
   hilo aceptando, **Then** el sistema captura la respuesta y cambia el estado a "Aceptada" (Ilustración
   10).
2. **Given** una cotización aceptada, **When** el Administrador marca la entrega del servicio/producto,
   **Then** el estado avanza a "Entregada" (Ilustración 11).
3. **Given** una cotización entregada, **When** el Administrador gestiona la facturación, **Then** el
   estado avanza a "Facturada" y el caso queda cerrado con historial completo consultable.
4. **Given** un cliente que responde fuera del hilo original (correo nuevo, no una respuesta), **When** el
   sistema recibe ese mensaje, **Then** genera una notificación interna al Administrador indicando que hay
   un correo no vinculable a un hilo existente, para que lo documente manualmente (ver Clarifications).

---

### User Story 4 - Solicitudes de compra a proveedores (Priority: P2)

Además de las cotizaciones a clientes, el sistema gestiona solicitudes de compra a proveedores, con su
propio flujo de recepción, cotización, aprobación y facturación.

**Why this priority**: Explícito en el Módulo 5 de la cotización como flujo paralelo al de cotizaciones a
clientes, reutilizando el mismo patrón de estados pero con `PROVEEDORES`/`COMPRAS` en vez de `CLIENTES`.

**Independent Test**: Se registra una solicitud de compra a un proveedor, se aprueba y se factura,
verificando que el historial de costos queda registrado en `COMPRAS`/`DETALLE_COMPRA`.

**Acceptance Scenarios**:

1. **Given** una necesidad de compra identificada (ej. reposición de inventario), **When** se registra una
   solicitud de compra a un proveedor con los insumos y cantidades requeridas, **Then** el sistema la
   registra en estado "Recepción".
2. **Given** una solicitud de compra en curso, **When** avanza por cotización → aprobación → facturación,
   **Then** cada transición queda registrada con fecha y responsable.
3. **Given** una compra facturada, **When** se consulta el historial de proveedores, **Then** se puede ver
   el costo total y detalle de ítems comprados (`DETALLE_COMPRA`).

### Edge Cases

- ¿Qué pasa si un cliente responde con contra-oferta o pide modificar ítems de la cotización? El sistema
  debe permitir reabrir/editar la cotización desde "Cotizada" sin perder el historial de versiones previas.
- ¿Se permite tener más de una cuenta de correo oficial monitoreada simultáneamente, o solo una (según nota
  3 de la cotización, "al menos una cuenta de correo electrónico oficial")?
- Mecanismo técnico de recepción/envío de correo: resuelto — IMAP polling (ver Clarifications). El envío
  saliente usa SMTP estándar de Laravel (Mail), consistente con el proveedor de correo de la cuenta
  oficial.
- Identificación automática del cliente remitente: [NEEDS CLARIFICATION: ¿se matchea por dominio/correo
  exacto contra `CLIENTES.correo`, o requiere selección manual si el remitente no está registrado? — punto
  aún abierto, no crítico para bloquear `/speckit-plan`, puede resolverse durante el diseño técnico].

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE registrar automáticamente como caso gestionable toda solicitud de cotización
  recibida en la cuenta de correo oficial configurada, siguiendo la plantilla esperada.
- **FR-002**: El sistema DEBE permitir construir cotizaciones seleccionando ítems (servicios/insumos) desde
  una maestra preconfigurada, con cálculo automático de totales.
- **FR-003**: El sistema DEBE permitir guardar una cotización como borrador sin enviarla, y retomarla
  posteriormente para edición.
- **FR-004**: El sistema DEBE permitir enviar la cotización al cliente por correo directamente desde la
  plataforma, adjuntando el documento generado y quedando asociada al hilo de comunicación.
- **FR-005**: El sistema DEBE capturar automáticamente la respuesta del cliente (aceptación/rechazo) cuando
  responde en el mismo hilo de correo, actualizando el estado del caso sin intervención manual.
- **FR-006**: El sistema DEBE permitir avanzar manualmente el caso por los estados posteriores a la
  aceptación: entrega y facturación.
- **FR-007**: El sistema DEBE mantener el historial completo de interacción (correos, cambios de estado,
  comentarios) de cada caso de cotización, visible en una vista tipo hilo (Ilustraciones 6-11).
- **FR-008**: El sistema DEBE gestionar solicitudes de compra a proveedores con flujo propio: recepción,
  cotización, aprobación, facturación, y registro de costos (`COMPRAS`, `DETALLE_COMPRA`).
- **FR-010**: El sistema DEBE recibir las solicitudes de cotización mediante un job programado que consulta
  vía IMAP la cuenta de correo oficial configurada (polling), implementado detrás de una interfaz de
  "proveedor de correo entrante" para no acoplar el resto del módulo al protocolo específico.
- **FR-011**: El sistema DEBE notificar internamente al Administrador cuando reciba un correo que no
  corresponda a ningún hilo/caso de cotización existente, en lugar de descartarlo.

### Key Entities

- **Compra** (`COMPRAS`): id, proveedor_id (spec 000), fecha, estado, observaciones.
- **Detalle de Compra** (`DETALLE_COMPRA`): id, compra_id, inventario_id, cantidad, costo_unitario,
  valor_total.
- **Cotización**: entidad propia e independiente de `ORDENES_TRABAJO` (no existe en el ER original — se
  incorpora en `/speckit-plan`). Representa el caso comercial completo: cliente, equipo (opcional), estado
  (En revisión, Cotizada, Aceptada/Rechazada, Entregada, Facturada), correo original, e ítems cotizados.
- **Detalle de Cotización**: ítems (servicios/insumos) de una Cotización, con cantidad, costo unitario y
  total — análogo a `DETALLE_COMPRA` pero orientado al cliente.
- **Mensaje de Cotización**: cada entrada del hilo de comunicación (correo entrante/saliente o comentario
  interno) asociada a una Cotización, con autor, fecha y contenido.
- Referencia a **Cliente** y **Proveedor**, propiedad del [spec 000](../000-catalogos-maestros/spec.md).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de los correos de solicitud de cotización que siguen la plantilla esperada se
  registran como caso gestionable sin intervención manual.
- **SC-002**: El Administrador puede construir y enviar una cotización completa (con ítems de la maestra)
  en menos de 5 minutos.
- **SC-003**: El 90% de las respuestas de clientes en el mismo hilo se capturan automáticamente sin que el
  Administrador tenga que actualizar el estado manualmente.
- **SC-004**: El historial de cada caso de cotización permite reconstruir el proceso comercial completo
  (recepción → cotización → respuesta → entrega → facturación) sin consultar el correo externo.

## Assumptions

- Existe (o existirá antes del desarrollo de este módulo) al menos una cuenta de correo electrónico oficial
  dedicada, según lo exige la nota 3 de la cotización, con credenciales IMAP/SMTP disponibles para el
  sistema (no depende de que sea Gmail Workspace ni de un proveedor transaccional específico).
- El cliente responde dentro del mismo hilo de correo en la mayoría de los casos; el flujo manual descrito
  en la cotización es el mecanismo de respaldo para el resto.
- La maestra de servicios/insumos usada para cotizaciones puede reutilizar el catálogo de `INVENTARIO`
  (spec 003) para los insumos, y requiere un catálogo adicional de "servicios" no modelado aún en el ER.
