# Feature Specification: Reportes e Indicadores (KPIs)

**Feature Branch**: `007-reportes-kpis`

**Created**: 2026-08-24

**Status**: Draft

**Input**: Cotización SERVIREPARAR, Módulo 6 — Reportes e Indicadores (KPIs); Fase 3 del cronograma;
Mockups Ilustraciones 5, 12 (dashboards de Administrador y Jefe de Taller como precedente visual).

## Clarifications

### Session 2026-08-24

- Q: ¿Qué tan "en tiempo real" debe ser el dashboard de KPIs para el primer corte funcional? → A: Recarga
  al navegar/refrescar — los indicadores se recalculan por consulta directa a base de datos cada vez que el
  usuario entra o refresca la página, sin polling ni websockets.
- Q: ¿Qué exportaciones son prioritarias para el primer corte funcional del módulo? → A: OT e Inventario
  primero (Fase 2, módulos centrales); Compras/Cotizaciones y Personal se exportan en un corte posterior.

### Session 2026-09-15

- Q: El usuario pidió visibilizar el trabajo en ejecución de forma más "en vivo" que la recarga manual del
  corte anterior. ¿Cómo se amplía el alcance sin incorporar la infraestructura de websockets que la
  cotización no contempla? → A: Auto-refresh con `wire:poll` de Livewire (15–30 s) en los dashboards por
  rol y en la vista de "trabajo en ejecución" del Jefe de Taller, en lugar de solo recarga manual. Sigue
  sin requerir Laravel Reverb/Echo ni WebSockets; solo cambia la cadencia de la consulta directa a base de
  datos ya definida en la sesión anterior.

### Session 2026-09-26 (drill-downs del panel y auditoría, documentado retroactivamente)

> Esta sección documenta funcionalidad que ya estaba construida y en producción (commits `66eb2f4`/
> `cff962b`, 2026-09-25/26) sin haber pasado por `/speckit-clarify` — se registra aquí para que el spec
> refleje el comportamiento real del sistema.

- Q: Las tarjetas del dashboard (US1) mostraban solo un número — ¿alcanza para operar, o se necesita ver el
  detalle detrás de cada cifra? → A: No alcanza. Se agrega un panel de operación (embebido en el dashboard
  de Administrador/Jefe de Taller) donde cada tarjeta es clicable y abre un modal con el listado real
  detrás del número (las OT de esa categoría, los técnicos libres/ocupados, las herramientas pendientes,
  etc.), en vez de solo mostrar la cifra.
- Q: El desempeño por técnico (spec 004, US3) ya tenía una pantalla propia (`/personal/desempeno`) —
  ¿se duplica en el panel? → A: No se duplica la lógica de cálculo (sigue en
  `DesempenoTecnicoService`), pero el panel agrega un acceso rápido inline (lista de técnicos → detalle de
  tareas de uno) para no salir del dashboard a consultarlo.
- Q: ¿Se necesita ver la actividad reciente del sistema en un solo lugar, más allá de la bitácora por OT
  individual que ya existía dentro del detalle? → A: Sí — se agrega una pantalla de auditoría
  (`/reportes/auditoria`, solo Administrador) que unifica la bitácora append-only de eventos de OT
  (`ot_eventos`, ya existente desde spec 002) con filtros por tipo de evento, usuario y rango de fechas, sin
  crear una tabla nueva. No cubre inventario ni usuarios (esos módulos no tienen bitácora hoy).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Dashboard en tiempo real con indicadores clave (Priority: P1)

Cada rol (Administrador, Jefe de Taller, Almacenista) visualiza al ingresar un dashboard con los
indicadores más relevantes de su operación, consistente con los mockups ya diseñados (Ilustraciones 5, 12,
17).

**Why this priority**: Ya está validado como patrón de UI en los 3 flujos mockeados; es la forma en que el
usuario interactúa con los KPIs día a día, más que reportes exportables.

**Independent Test**: Con datos de OT, inventario y cotizaciones cargados, se verifica que el dashboard de
cada rol refleja las cifras correctas (ej. "OT vencidas: 2" coincide con el conteo real en base de datos).

**Acceptance Scenarios**:

1. **Given** el Jefe de Taller autenticado, **When** ingresa al sistema, refresca la página o transcurre el
   ciclo de auto-refresh (`wire:poll`, 15–30 s), **Then** ve OT activas, próximas a vencer y vencidas,
   calculadas por consulta directa al estado actual de las OT.
2. **Given** el Administrador autenticado, **When** ingresa al sistema, **Then** ve cotizaciones activas,
   próximas a vencer y herramientas dañadas pendientes de gestión.
3. **Given** el Almacenista autenticado, **When** ingresa al sistema, **Then** ve solicitudes pendientes,
   insumos por entregar y herramientas pendientes de devolución.
4. **Given** el Administrador o Jefe de Taller viendo una tarjeta con una cifra agregada (OT por categoría,
   herramientas pendientes, préstamos sin devolver, stock bajo, técnicos libres/ocupados, OT estancadas,
   despachos pendientes), **When** hace clic sobre ella, **Then** el sistema abre un modal con el listado
   real de los registros detrás de esa cifra, sin navegar a otra pantalla.
5. **Given** el Administrador viendo el panel, **When** abre el drill-down de desempeño, **Then** ve la
   lista de técnicos y, al elegir uno, sus tareas y métricas (spec 004, US3) sin salir del dashboard.

---

### User Story 2 - Indicadores agregados de operación (Priority: P1)

El sistema calcula y expone indicadores agregados: OT abiertas/cerradas/vencidas, cumplimiento de tiempos,
productividad del equipo y estado del inventario.

**Why this priority**: Es el listado explícito de KPIs de la cotización; da visibilidad gerencial más allá
del dashboard operativo diario.

**Independent Test**: Con un conjunto de OT de prueba con distintos estados y tiempos, se verifica que el
indicador de "cumplimiento de tiempos" calcula correctamente el porcentaje de OT cerradas dentro del tiempo
estimado.

**Acceptance Scenarios**:

1. **Given** un conjunto de OT históricas, **When** se consulta el indicador de cumplimiento de tiempos,
   **Then** el sistema muestra el porcentaje de OT finalizadas dentro del tiempo estimado vs. el total.
2. **Given** el estado actual del inventario, **When** se consulta el indicador correspondiente, **Then**
   se muestra el resumen de ítems en stock bajo, herramientas dañadas/en mantenimiento y disponibles.
3. **Given** un rango de fechas seleccionado, **When** se consulta productividad del equipo, **Then** se
   muestra el indicador agregado consistente con las métricas individuales de la spec 004.

---

### User Story 3 - Exportación y filtros avanzados (Priority: P2)

El usuario puede exportar reportes a Excel y PDF, aplicando filtros avanzados (fechas, cliente, técnico,
estado, tipo de servicio).

**Why this priority**: Explícito en la cotización; necesario para uso administrativo/gerencial fuera del
sistema (ej. reuniones, auditorías externas) pero no bloquea la operación diaria.

**Independent Test**: Se aplican filtros a un listado de OT y se exporta a Excel, verificando que el
archivo generado refleja exactamente el subconjunto filtrado en pantalla.

**Acceptance Scenarios**:

1. **Given** un listado de OT filtrado por estado y rango de fechas, **When** el usuario exporta a Excel,
   **Then** el archivo descargado contiene exactamente los registros mostrados en pantalla.
2. **Given** el mismo listado filtrado, **When** el usuario exporta a PDF, **Then** el documento generado
   mantiene formato legible con los mismos datos.

### User Story 4 - Auditoría de actividad reciente (Priority: P3)

El Administrador consulta en un solo lugar la actividad reciente del sistema (creación de OT, cambios de
estado, correcciones, salidas, entregas, gestión de insumos), con filtros por tipo de evento, usuario y
rango de fechas.

**Why this priority**: Es un pulido de producto sobre la bitácora append-only que ya existe por OT
individual (spec 002) — útil para supervisión, pero no bloquea ninguna operación diaria.

**Independent Test**: Con varios eventos de distintas OT registrados, el Administrador filtra por tipo de
evento y rango de fechas, y verifica que el listado coincide exactamente con los eventos de `ot_eventos`
que cumplen esos filtros.

**Acceptance Scenarios**:

1. **Given** el Administrador autenticado, **When** entra a `/reportes/auditoria`, **Then** ve un listado
   paginado de eventos de OT de todo el sistema (no solo de una OT), ordenado del más reciente al más
   antiguo.
2. **Given** el listado de auditoría, **When** el Administrador filtra por tipo de evento, usuario y/o rango
   de fechas, **Then** el listado se acota exactamente a los eventos que cumplen esos filtros.
3. **Given** un usuario sin el permiso de auditoría (cualquier rol distinto de Administrador), **When**
   intenta acceder a `/reportes/auditoria`, **Then** el sistema lo rechaza (403).

### Edge Cases

- ¿Qué pasa si el volumen de datos histórico crece significativamente? Los reportes deben paginar/limitar
  sin degradar el rendimiento del dashboard en tiempo real.
- Prioridad de exportaciones: OT e Inventario en el primer corte; Compras/Cotizaciones y Personal en un
  corte posterior (ver Clarifications).
- Nivel de "tiempo real": recarga al navegar/refrescar + auto-refresh con `wire:poll` (15–30 s), sin
  websockets (ver Clarifications, sesión 2026-09-15).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE mostrar, al ingresar cada rol, un dashboard con los indicadores relevantes
  para ese rol (según lo validado en los mockups Ilustraciones 5, 12, 17).
- **FR-002**: El sistema DEBE calcular el indicador de OT abiertas, cerradas y vencidas.
- **FR-003**: El sistema DEBE calcular el indicador de cumplimiento de tiempos (OT finalizadas dentro del
  tiempo estimado vs. total).
- **FR-004**: El sistema DEBE calcular el indicador de productividad del equipo, consistente con las
  métricas individuales por técnico (spec 004).
- **FR-005**: El sistema DEBE calcular el indicador de estado del inventario (disponible, en uso, dañado,
  en mantenimiento, stock bajo).
- **FR-006**: El sistema DEBE permitir exportar a Excel y PDF los listados de Órdenes de Trabajo e
  Inventario en el primer corte funcional; las exportaciones de Compras/Cotizaciones y Personal se
  implementan en un corte posterior.
- **FR-007**: El sistema DEBE permitir aplicar filtros avanzados (fecha, cliente, técnico, estado, tipo de
  servicio) a los listados antes de exportar o visualizar.
- **FR-008**: Los indicadores del dashboard y de reportes agregados DEBEN calcularse mediante consulta
  directa a la base de datos, tanto en cada carga/refresco de página como en el auto-refresh periódico
  (`wire:poll`, 15–30 s) de los dashboards por rol y de la vista de "trabajo en ejecución"; el alcance
  contratado NO requiere WebSockets/Laravel Reverb (ver Clarifications, sesión 2026-09-15).

- **FR-009**: Las tarjetas de indicadores agregados del dashboard de Administrador/Jefe de Taller DEBEN ser
  clicables y abrir el listado real de registros detrás de la cifra (drill-down), en vez de mostrar solo el
  número.
- **FR-010**: El sistema DEBE ofrecer una pantalla de auditoría (`/reportes/auditoria`, exclusiva del
  Administrador) que unifique la bitácora de eventos de OT (`ot_eventos`, spec 002) de todas las OT, con
  filtros por tipo de evento, usuario y rango de fechas.

### Key Entities

Este módulo no introduce entidades propias; consume y agrega datos de `ORDENES_TRABAJO`/`DETALLE_OT` (spec
002, incluida su bitácora `OT_EVENTOS` para la auditoría de FR-010), `INVENTARIO`/`MOVIMIENTOS_INVENTARIO`
(spec 003), `TECNICOS` (spec 004) y `COMPRAS` (spec 006).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Los indicadores del dashboard reflejan el estado real del sistema en el momento exacto de
  carga/refresco de la página o del último ciclo de auto-refresh (sin desfase mayor a 15–30 s, al
  calcularse siempre por consulta directa, no por caché).
- **SC-002**: Un usuario puede generar una exportación filtrada (Excel o PDF) en menos de 30 segundos para
  volúmenes de datos típicos de la operación.
- **SC-003**: El 100% de los indicadores mostrados coinciden con el conteo manual/consulta directa a base
  de datos durante pruebas de validación.

## Assumptions

- Los KPIs se calculan sobre datos ya existentes en el sistema (OT, inventario, personal, compras); no se
  requiere una fuente de datos externa adicional.
- Este módulo se implementa después de que los módulos de origen de datos (002, 003, 004, 006) tengan al
  menos su modelo de datos definido, dado que consume esas entidades.
