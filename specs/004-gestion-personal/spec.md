# Feature Specification: Gestión de Personal

**Feature Branch**: `004-gestion-personal`

**Created**: 2026-08-24

**Status**: Draft

**Input**: Cotización SERVIREPARAR, Módulo 3 — Gestión de Personal; Fase 2 del cronograma; ER: `TECNICOS`,
`USUARIOS`, `ROLES`, `ORDENES_TRABAJO`.

## Clarifications

### Session 2026-08-24

- Q: El campo "especialidad" del técnico, ¿debe ser un catálogo fijo o texto libre? → A: Catálogo fijo
  predefinido, gestionado en el sistema, para permitir filtrar/reportar técnicos por especialidad de forma
  consistente.

### Session 2026-08-25

- Q: ¿La gestión de técnicos (`TECNICOS`) debe tener su propia pantalla ("Empleados") separada de la
  pantalla de Usuarios (spec 001)? → A: No. Debe vivir en la misma pantalla de Usuarios: el Administrador
  filtra ahí por rol (incluyendo Técnico) y por estado activo/inactivo, y edita los campos propios del
  técnico (especialidad, estado en `TECNICOS`) dentro del mismo formulario cuando el usuario tiene el rol
  Técnico asignado. No debe existir una pantalla ni un menú de navegación separados para "Empleados".
- Q: "Catálogo fijo predefinido, gestionado en el sistema" (sesión 2026-08-24) — ¿significa sembrado una
  vez por un seeder/desarrollador, o el Administrador debe poder mantenerlo desde la interfaz? → A: Desde
  la interfaz. Un catálogo que solo se edita por seeder es exactamente el riesgo que se quería evitar
  (nombres distintos para lo mismo por error de digitación al no poder corregirlo sin intervención técnica).
  El Administrador crea/edita/inactiva especialidades desde una pantalla propia; inactivar una especialidad
  no afecta a los técnicos que ya la tienen asignada, solo deja de ofrecerse para asignaciones nuevas.

### Session 2026-09-01 (costeo de mano de obra por día y hoja de vida del técnico)

- Q: ¿La mano de obra propia en la OT se costea por hora o por día? → A: **Por día**. El técnico tiene un
  **sueldo mensual**; el valor del día se deriva automáticamente como `sueldo / 30` (el divisor 30 queda
  en configuración). La OT registra **cantidad de días** trabajados por técnico, no horas. Se elimina el
  campo `tarifa_hora` manual que existía; el valor día NO se edita a mano, siempre sale del sueldo.
- Q: Si a un técnico le suben el sueldo, ¿qué pasa con las OT que ya trabajó a un sueldo menor? → A: Las
  OT **ya cerradas** conservan su costo de mano de obra calculado con el sueldo que estaba vigente a su
  fecha de cierre — no se recalculan. Las OT **en curso** y **futuras** usan el sueldo vigente actual. Para
  eso se guarda un **histórico de sueldos** por técnico (`sueldo` + `vigente_desde`); el valor día de una
  OT se calcula con el sueldo vigente a la fecha de referencia de esa OT (su fecha de cierre, o la fecha
  actual si sigue abierta).
- Q: ¿Se amplía la ficha del técnico? → A: Sí. Se agregan **datos laborales** (fecha de ingreso, cargo,
  tipo de contrato: término fijo / indefinido / prestación de servicios) y una vista de **hoja de vida**:
  un panel de **solo lectura** abierto desde la fila del usuario Técnico en la pantalla de Usuarios
  (respeta FR-007: sin pantalla ni menú separados de "Empleados"), con especialidad, sueldo actual, valor
  día, estado, datos laborales, histórico de sueldos y el resumen operativo (carga actual + desempeño, que
  se llena cuando exista el módulo de OT — spec 002).
- Q: El divisor y la estimación de tiempo de la tarea, ¿en horas o días? → A: **Todo en días** — el costeo,
  la estimación de la tarea y el umbral de vencimiento (spec 008) pasan a días. Impacta el modelo de datos
  de spec 002 (`detalle_ot`: `tiempo_estimado_dias`, `dias_trabajados`).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Registrar y configurar trabajadores (Priority: P1)

El Administrador registra trabajadores (técnicos) del taller, asociándolos a un usuario del sistema y
definiendo su especialidad, sueldo mensual, datos laborales (fecha de ingreso, cargo, tipo de contrato) y
estado de actividad.

**Why this priority**: Prerrequisito para que existan técnicos asignables a tareas de OT (spec 002); sin
esto el módulo central de la operación no puede funcionar con datos reales.

**Independent Test**: Se crea un usuario con rol Técnico y su registro correspondiente en `TECNICOS` con
especialidad, y se verifica que aparece disponible como operario al crear una tarea de OT.

**Acceptance Scenarios**:

1. **Given** el Administrador autenticado, **When** registra un trabajador con especialidad, sueldo mensual
   y lo marca activo, **Then** el trabajador queda disponible en los selectores de "operario" al crear
   tareas de OT, y su **valor día** (`sueldo / 30`) queda calculado para el costeo de mano de obra.
2. **Given** un trabajador marcado como inactivo, **When** se intenta asignarlo a una nueva tarea de OT,
   **Then** el sistema no lo ofrece como opción disponible.
3. **Given** un técnico con un sueldo registrado, **When** el Administrador le cambia el sueldo, **Then** el
   sistema guarda el nuevo sueldo con su fecha de vigencia sin borrar el anterior; las OT cerradas antes de
   esa fecha siguen costeadas con el sueldo anterior y las OT abiertas o nuevas usan el nuevo.

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

---

### User Story 4 - Hoja de vida del técnico (Priority: P2)

El Administrador abre, desde la fila de un usuario con rol Técnico en la pantalla de Usuarios, un panel de
**solo lectura** que resume la "hoja de vida" del trabajador: especialidad, sueldo actual y valor día,
estado, datos laborales (fecha de ingreso, cargo, tipo de contrato), histórico de sueldos, y el resumen
operativo (carga actual + desempeño).

**Why this priority**: Da una vista consolidada del trabajador para decisiones de asignación y gestión de
personal, sin abrir un módulo de "Empleados" separado (prohibido por FR-007). No bloquea la operación
diaria.

**Independent Test**: Con un técnico que tiene especialidad, sueldo y datos laborales cargados, el
Administrador abre su hoja de vida y verifica que muestra el sueldo actual, el valor día calculado
(`sueldo / 30`), los datos laborales y el histórico de sueldos; el panel no permite editar nada.

**Acceptance Scenarios**:

1. **Given** un usuario con rol Técnico y ficha completa, **When** el Administrador pulsa "Hoja de vida" en
   su fila, **Then** se abre un panel de solo lectura con especialidad, sueldo actual, valor día, estado,
   fecha de ingreso, cargo, tipo de contrato e histórico de sueldos.
2. **Given** un usuario sin rol Técnico, **When** el Administrador ve su fila, **Then** no se ofrece la
   opción "Hoja de vida".
3. **Given** un técnico con OT en su historial (spec 002), **When** se abre su hoja de vida, **Then** el
   panel incluye su carga actual (tareas activas) y su resumen de desempeño; mientras spec 002 no exista,
   esa sección muestra un aviso de "disponible al implementar Órdenes de Trabajo".

### Edge Cases

- ¿Qué pasa con las métricas de desempeño de un técnico que es inactivado? Deben conservarse históricamente
  aunque el técnico ya no esté activo.
- Un usuario puede tener rol Técnico y simultáneamente otro rol (ej. Jefe de Taller que también ejecuta
  tareas): la ficha de `TECNICOS` debe poder coexistir con múltiples roles del mismo usuario (ver spec 001).
- Especialidad: catálogo fijo predefinido, mantenido en el sistema (ver Clarifications), no texto libre.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE permitir registrar un trabajador vinculado a un usuario existente, con
  especialidad (seleccionada de un catálogo fijo predefinido, mantenido por el Administrador) y estado
  activo/inactivo (`TECNICOS`).
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
- **FR-007**: El sistema NO DEBE presentar una pantalla ni un ítem de menú de navegación separados
  ("Empleados") para gestionar técnicos. El alta, edición y consulta de datos de `TECNICOS` (especialidad,
  activo) DEBE hacerse desde la pantalla de gestión de Usuarios (spec 001, User Story 4), filtrando por
  rol Técnico y por estado activo/inactivo.
- **FR-008**: El sistema DEBE permitir al Administrador crear, editar e inactivar entradas del catálogo de
  Especialidad desde una pantalla propia (no un seeder ni intervención técnica), evitando nombres distintos
  para la misma especialidad por error de digitación. Inactivar una especialidad NO DEBE afectar a los
  técnicos que ya la tienen asignada; solo deja de ofrecerse como opción para asignaciones nuevas.
- **FR-009**: El sistema DEBE registrar el **sueldo mensual** de cada técnico y conservar su **histórico**:
  cada cambio de sueldo se guarda como un registro nuevo con su fecha de vigencia (`vigente_desde`), sin
  borrar los anteriores. El "sueldo actual" es el registro de mayor `vigente_desde` menor o igual a la
  fecha de hoy.
- **FR-010**: El sistema DEBE derivar automáticamente el **valor del día** de un técnico como
  `sueldo_mensual / N`, con `N` configurable (por defecto 30). El valor día NO se edita manualmente; se
  recalcula solo cuando cambia el sueldo. Toda presentación de sueldo o valor día DEBE usar el formato de
  pesos colombianos (`App\Support\Moneda`, spec 009 FR-001).
- **FR-011**: El costeo de mano de obra propia de una Orden de Trabajo (spec 002) DEBE usar el valor día
  del sueldo **vigente a la fecha de referencia de esa OT**: su fecha de cierre si ya está cerrada, o la
  fecha actual si sigue abierta. Un cambio de sueldo posterior al cierre de una OT NO DEBE alterar el costo
  de mano de obra ya calculado para esa OT.
- **FR-012**: El registro de técnico DEBE incluir **datos laborales**: fecha de ingreso, cargo y tipo de
  contrato (término fijo / indefinido / prestación de servicios). Son informativos (hoja de vida); no
  afectan el costeo.
- **FR-013**: El sistema DEBE ofrecer una **hoja de vida** del técnico como panel de **solo lectura**,
  accesible desde la fila del usuario con rol Técnico en la pantalla de Usuarios (FR-007), que consolida
  especialidad, sueldo actual, valor día, estado, datos laborales, histórico de sueldos y el resumen
  operativo (carga actual + desempeño, FR-003/FR-005). NO DEBE existir una pantalla ni un ítem de menú
  separados para esta hoja de vida.

### Key Entities

- **Técnico** (`TECNICOS`): id, usuario_id (FK a `USUARIOS`), especialidad_id (FK a catálogo de
  especialidades), fecha_ingreso (date, nullable), cargo (string, nullable), tipo_contrato
  (`termino_fijo` / `indefinido` / `prestacion_servicios`, nullable), activo. El campo `tarifa_hora` queda
  eliminado (reemplazado por el histórico de sueldos + valor día derivado).
- **Sueldo de Técnico** (`SUELDOS_TECNICO`): id, tecnico_id (FK), sueldo (decimal, mensual), vigente_desde
  (date), registrado_por (FK a `USUARIOS`, nullable), timestamps. Historial de sueldos (FR-009); el valor
  día se deriva de aquí (FR-010).
- **Especialidad**: catálogo fijo predefinido (nombre) mantenido por el Administrador.
- Relación indirecta con **Orden de Trabajo** / **Detalle de OT** (spec 002) para el cálculo de desempeño y
  el costeo de mano de obra por día (FR-011).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El Administrador puede generar el reporte de desempeño de cualquier técnico activo en menos
  de 10 segundos.
- **SC-002**: El 100% de los técnicos inactivos quedan excluidos de los selectores de asignación de nuevas
  tareas.
- **SC-003**: Las métricas de desempeño reflejan con exactitud el historial de OT (validable contra el
  conteo manual de OT finalizadas por técnico en un período de prueba).
- **SC-004**: Tras subir el sueldo de un técnico, el costo de mano de obra de cualquier OT que ya estaba
  cerrada permanece idéntico al que tenía antes del cambio (verificable recalculándolo desde el histórico
  de sueldos y la fecha de cierre de la OT).
- **SC-005**: El valor día mostrado para un técnico siempre es exactamente `sueldo_actual / N` (N por
  defecto 30), y todo monto (sueldo, valor día) se muestra formateado en pesos colombianos.

## Assumptions

- Un "trabajador"/técnico siempre corresponde 1:1 a un `USUARIO` con rol Técnico asignado (no se manejan
  técnicos externos sin cuenta en el sistema).
- Las métricas de productividad se calculan sobre datos de OT ya existentes en el sistema (no se requiere
  integración con nómina o control de asistencia).
- El sueldo es el sueldo mensual base pactado; el valor día = `sueldo / 30` es una convención de costeo
  interno del taller, no un cálculo de nómina con factor prestacional (si más adelante se quiere incluir
  carga prestacional, se ajusta el divisor `N` en configuración o se agrega un factor).
- La "fecha de referencia" de una OT para elegir el sueldo vigente es su fecha de cierre; mientras la OT
  está abierta se usa el sueldo vigente hoy, por lo que su costo de mano de obra puede variar hasta que se
  cierre (momento en que queda fijo).
