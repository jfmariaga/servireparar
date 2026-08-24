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

1. **Given** el Jefe de Taller autenticado, **When** ingresa al sistema o refresca la página, **Then** ve
   OT activas, próximas a vencer y vencidas, calculadas por consulta directa al estado actual de las OT (sin
   necesidad de actualización automática en segundo plano).
2. **Given** el Administrador autenticado, **When** ingresa al sistema, **Then** ve cotizaciones activas,
   próximas a vencer y herramientas dañadas pendientes de gestión.
3. **Given** el Almacenista autenticado, **When** ingresa al sistema, **Then** ve solicitudes pendientes,
   insumos por entregar y herramientas pendientes de devolución.

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

### Edge Cases

- ¿Qué pasa si el volumen de datos histórico crece significativamente? Los reportes deben paginar/limitar
  sin degradar el rendimiento del dashboard en tiempo real.
- Prioridad de exportaciones: OT e Inventario en el primer corte; Compras/Cotizaciones y Personal en un
  corte posterior (ver Clarifications).
- Nivel de "tiempo real": recarga al navegar/refrescar, sin polling ni websockets (ver Clarifications).

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
  directa a la base de datos en cada carga/refresco de página, sin requerir mecanismos de actualización
  automática en segundo plano (polling/websockets) en el alcance contratado.

### Key Entities

Este módulo no introduce entidades propias; consume y agrega datos de `ORDENES_TRABAJO`/`DETALLE_OT` (spec
002), `INVENTARIO`/`MOVIMIENTOS_INVENTARIO` (spec 003), `TECNICOS` (spec 004) y `COMPRAS` (spec 006).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Los indicadores del dashboard reflejan el estado real del sistema en el momento exacto de
  carga/refresco de la página (sin desfase, al calcularse por consulta directa, no por caché o polling).
- **SC-002**: Un usuario puede generar una exportación filtrada (Excel o PDF) en menos de 30 segundos para
  volúmenes de datos típicos de la operación.
- **SC-003**: El 100% de los indicadores mostrados coinciden con el conteo manual/consulta directa a base
  de datos durante pruebas de validación.

## Assumptions

- Los KPIs se calculan sobre datos ya existentes en el sistema (OT, inventario, personal, compras); no se
  requiere una fuente de datos externa adicional.
- Este módulo se implementa después de que los módulos de origen de datos (002, 003, 004, 006) tengan al
  menos su modelo de datos definido, dado que consume esas entidades.
