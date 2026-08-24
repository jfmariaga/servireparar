# Feature Specification: Notificaciones y Alertas

**Feature Branch**: `008-notificaciones-alertas`

**Created**: 2026-08-24

**Status**: Draft

**Input**: Cotización SERVIREPARAR, Módulo 7 — Notificaciones y Alertas; Fase 3 del cronograma; ER:
`NOTIFICACIONES`, `CONFIGURACIONES`, `USUARIOS`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Recibir notificaciones internas dentro del sistema (Priority: P1)

Cada usuario ve, mediante el ícono de campana visible en todos los mockups (Ilustraciones 5, 12, 17), sus
notificaciones internas pendientes (leídas/no leídas) relacionadas con su rol y sus responsabilidades.

**Why this priority**: Es el mecanismo transversal que conecta las alertas generadas por los demás módulos
(OT vencidas, stock bajo, mantenimientos próximos, solicitudes pendientes) con el usuario final; sin esto
esos módulos generan datos pero no comunican nada.

**Independent Test**: Se genera un evento que debería notificar a un usuario (ej. una OT que vence) y se
verifica que aparece en su lista de notificaciones con estado "no leída".

**Acceptance Scenarios**:

1. **Given** un evento generador de alerta (ej. stock bajo de un consumible), **When** ocurre, **Then** se
   crea una notificación interna para el/los usuario(s) con el rol relevante (Almacenista).
2. **Given** una notificación no leída, **When** el usuario la abre o la marca como leída, **Then** su
   estado cambia a "leída" y deja de contarse como pendiente en el ícono de campana.
3. **Given** una notificación asociada a un caso (ej. una OT específica), **When** el usuario hace clic
   sobre ella, **Then** es dirigido directamente al detalle del caso correspondiente.

---

### User Story 2 - Alertas automáticas por vencimiento y stock (Priority: P1)

El sistema genera automáticamente notificaciones para: OT próximas a vencer/vencidas, stock bajo de
consumibles, mantenimientos preventivos próximos, y solicitudes de compra/cotización pendientes.

**Why this priority**: Es el contenido funcional explícito del módulo según la cotización; conecta
directamente con reglas de negocio ya definidas en los specs 002, 003, 005 y 006.

**Independent Test**: Se fuerza cada condición (OT vencida, stock bajo, mantenimiento próximo, solicitud
pendiente de aprobación) y se verifica que cada una genera su notificación correspondiente al rol
adecuado.

**Acceptance Scenarios**:

1. **Given** una OT cuyo tiempo estimado se cumple sin estar finalizada, **When** se cumple el umbral
   definido en spec 002, **Then** se genera una notificación de "OT próxima a vencer/vencida" al Jefe de
   Taller.
2. **Given** un consumible cuyo stock cae bajo el mínimo, **When** ocurre el movimiento, **Then** se genera
   una notificación de "stock bajo" al Almacenista.
3. **Given** un mantenimiento preventivo próximo a su fecha programada, **When** se cumple el umbral
   definido en spec 005, **Then** se genera una notificación al responsable correspondiente.
4. **Given** una solicitud de compra o cotización pendiente de acción, **When** permanece sin gestionar,
   **Then** se genera una notificación al rol responsable de atenderla.

---

### User Story 3 - Notificación automática al cliente por correo (Priority: P2)

El sistema envía notificaciones automáticas al correo del cliente en los hitos relevantes del proceso (ej.
creación de OT, entrega del servicio, envío de cotización).

**Why this priority**: Explícito en el Módulo 1 de la cotización ("notificación automática al cliente vía
correo") y usado activamente en el flujo de cotizaciones (spec 006); depende de la infraestructura de
correo saliente definida en ese módulo.

**Independent Test**: Se completa la entrega de una OT y se verifica que el cliente asociado recibe un
correo automático con el contenido esperado.

**Acceptance Scenarios**:

1. **Given** una OT que cambia a estado "Entregada", **When** ocurre la transición, **Then** el sistema
   envía un correo automático al cliente asociado.
2. **Given** una cotización enviada desde el módulo de Compras/Cotizaciones, **When** se envía, **Then** el
   correo llega al cliente con el documento adjunto (delegado a spec 006, reutilizando el mismo canal de
   envío).

### Edge Cases

- ¿Qué pasa si un usuario tiene múltiples roles y una alerta aplica a varios de ellos? No debe duplicarse
  la notificación innecesariamente.
- ¿Se requiere un centro de configuración para que cada usuario decida qué tipos de alerta recibir (email
  vs. solo interna), o las reglas son fijas por rol? La entidad `CONFIGURACIONES` del ER sugiere que sí
  existe algún nivel de configuración parametrizable por clave/valor.
- Umbrales exactos de cada alerta (OT, mantenimiento) dependen de decisiones tomadas en specs 002 y 005;
  este módulo debe consumir esos umbrales, no redefinirlos.
- ¿Qué pasa si el envío de correo falla (proveedor caído, correo inválido)? Debe quedar registrado como
  notificación interna fallida para reintento o revisión manual, sin perder el evento origen.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE generar una notificación interna (`NOTIFICACIONES`) para cada evento
  relevante: OT próxima a vencer/vencida, stock bajo, mantenimiento preventivo próximo, solicitud pendiente
  (compra/cotización/insumo).
- **FR-002**: El sistema DEBE dirigir cada notificación al usuario o rol responsable de atenderla, evitando
  duplicados innecesarios cuando un usuario tiene múltiples roles aplicables.
- **FR-003**: El sistema DEBE permitir marcar notificaciones como leídas/no leídas y mostrar un conteo de
  pendientes visible en todo momento (ícono de campana, según mockups).
- **FR-004**: El sistema DEBE permitir navegar desde una notificación directamente al caso/entidad
  relacionada.
- **FR-005**: El sistema DEBE enviar notificaciones automáticas por correo al cliente en los hitos
  definidos por los módulos de origen (creación/entrega de OT — spec 002; envío/respuesta de cotización —
  spec 006), reutilizando un único servicio de envío de correo saliente.
- **FR-006**: El sistema DEBE procesar el envío de notificaciones (internas y por correo) de forma
  asíncrona (colas), sin bloquear la acción del usuario que originó el evento.
- **FR-007**: El sistema DEBE registrar y permitir reintentar notificaciones por correo que fallaron en su
  envío.
- **FR-008**: El sistema DEBE soportar configuración a nivel de sistema (`CONFIGURACIONES`: clave, valor,
  descripción) para parámetros relacionados con notificaciones (ej. umbrales, remitente por defecto).

### Key Entities

- **Notificación** (`NOTIFICACIONES`): id, usuario_id, tipo, mensaje, leída (SI/NO), fecha_creacion.
- **Configuración** (`CONFIGURACIONES`): id, clave, valor, descripción.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de los eventos definidos (OT vencida, stock bajo, mantenimiento próximo, solicitud
  pendiente) generan su notificación interna correspondiente sin intervención manual.
- **SC-002**: El tiempo entre la ocurrencia del evento y la aparición de la notificación al usuario es
  menor a 1 minuto.
- **SC-003**: El 100% de los correos automáticos al cliente en hitos definidos se envían exitosamente o
  quedan registrados para reintento en caso de fallo (ningún evento se pierde silenciosamente).

## Assumptions

- El servicio de envío de correo saliente es compartido entre este módulo y el de Compras/Cotizaciones
  (spec 006); la decisión técnica del proveedor de correo se resuelve en el `/speckit-clarify` de ese
  módulo y aplica también aquí.
- Las notificaciones internas no requieren un canal push/tiempo real estricto (websockets); una
  actualización al navegar o refrescar la página es suficiente para el alcance contratado, salvo que se
  decida lo contrario en `/speckit-clarify`.
