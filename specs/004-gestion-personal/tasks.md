# Tasks: Gestión de Personal

**Input**: Design documents from `specs/004-gestion-personal/` (plan.md, spec.md)

**Prerequisites**: spec 001 (usuarios con rol Técnico) implementado

**Tests**: Incluidos — cálculos de desempeño requieren verificación contra datos conocidos.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 Confirmar que existen usuarios con rol Técnico de prueba (spec 001) en el entorno local — el rol
  ya está sembrado por `RolesSeeder` (spec 001) y usado en `tests/Feature/Personal/TecnicoTest.php`

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Bloquea las 3 historias de usuario

- [X] T002 Migración `create_especialidades_table`
- [X] T003 [P] Migración `create_tecnicos_table` (`usuario_id` unique, `especialidad_id`,
  `tarifa_hora`, `activo`)
- [X] T004 Seeder `database/seeders/EspecialidadesSeeder.php` (catálogo fijo inicial: Eléctrico, Mecánico,
  Refrigeración y aire acondicionado, Electrónico, Estructuras y soldadura, General)
- [X] T005 Modelos Eloquent: `Tecnico`, `Especialidad`
- [X] T006 `app/Policies/TecnicoPolicy.php` (gestión restringida a Administrador vía permiso
  `manage-tecnicos`)

**Checkpoint**: Modelo de datos listo — las historias de usuario pueden implementarse

---

## Phase 3: User Story 1 - Registrar y configurar trabajadores (Priority: P1) 🎯 MVP

**Goal**: Ficha de técnico vinculada a usuario existente, con especialidad de catálogo fijo y estado activo

**Independent Test**: Crear técnico vinculado a usuario con rol Técnico, verificar disponibilidad en
selector de operario de OT (o un selector de prueba si spec 002 aún no existe)

### Tests for User Story 1

- [X] T007 [P] [US1] Feature test `tests/Feature/Personal/TecnicoTest.php`: creación válida vinculada a
  usuario existente
- [X] T008 [P] [US1] Feature test: técnico inactivo excluido de selectores de asignación (FR-002) — cubierto
  por `Tecnico::scopeDisponibles()` y su test dedicado

### Implementation for User Story 1

- [X] T009 [US1] ~~Componente Volt `app/Livewire/Personal/Tecnicos.php`~~ — **decisión de diseño (spec 001
  FR-012 / spec 004 FR-007, 2026-08-25): sin pantalla separada de "Empleados"**. La ficha de técnico
  (especialidad, tarifa, activo) se integró directamente en `admin.usuarios.index`
  (`resources/views/livewire/admin/usuarios/index.blade.php`): aparece solo cuando el rol Técnico está
  marcado, con `especialidad_id` obligatorio en ese caso; la tabla de Usuarios muestra la especialidad como
  columna adicional
- [X] T010 [US1] ~~Ruta `/personal/tecnicos`~~ — no aplica (ver T009); protegida por `TecnicoPolicy::manage`
  (`Gate::authorize('manage', Tecnico::class)`) dentro de `guardar()`, además del `UserPolicy` ya existente
  en la ruta `/usuarios`

**Checkpoint**: Ficha de técnico funcional — MVP del módulo (prerrequisito de spec 002)

---

> **Estado (2026-08-25)**: Fases 4 y 5 (US2, US3) se difieren deliberadamente hasta que spec 002 exista.
> Construir `Tecnico::tareasActivasCount()` o `DesempenoTecnicoService` contra un `detalle_ot`/
> `ordenes_trabajo` que todavía no existe obligaría a adivinar el esquema de spec 002 ahora y muy
> probablemente reescribirlo después — más riesgo que valor. `Tecnico::scopeDisponibles()` (T008, ya
> implementado) es el único contrato que spec 002 necesita de este módulo para su selector de operario;
> el resto se retoma cuando spec 002 tenga `detalle_ot` real.

## Phase 4: User Story 2 - Asignación de trabajadores a Órdenes de Trabajo (Priority: P1)

**Goal**: Visibilidad de carga actual (tareas activas) de cada técnico al momento de asignar

**Independent Test**: Con dos técnicos (uno con 3 tareas activas, otro con 0 — datos de prueba si spec 002
aún no está completo), verificar que la carga es visible/consultable

### Tests for User Story 2

- [x] T011 [P] [US2] Feature test `tests/Feature/Personal/CargaTecnicoTest.php`: cálculo de tareas activas
  por técnico con datos de OT de prueba

### Implementation for User Story 2

- [x] T012 [US2] Método `Tecnico::tareasActivasCount()` consultando `detalle_ot` (spec 002) — mismo criterio
  de "activa" que `dashboard.blade.php` (estado_tarea pendiente/en_curso, OT liberada y no terminal)
- [x] T013 [US2] Carga expuesta en el selector de operario de `livewire/ordenes-trabajo/crear.blade.php`
  ("Nombre (N tareas activas)")

**Checkpoint**: US1 + US2 funcionan de forma independiente

---

## Phase 5: User Story 3 - Medir desempeño del personal (Priority: P2)

**Goal**: Reporte de tiempos de ejecución, participación en OT y productividad por técnico

**Independent Test**: Con historial de OT de prueba, generar reporte y verificar manualmente el comparativo
estimado vs. real y conteo de OT

### Tests for User Story 3

- [x] T014 [P] [US3] Feature test `tests/Feature/Personal/DesempenoTecnicoTest.php`: cálculo de tiempo
  promedio de ejecución, número de OT/tareas, comparativo estimado vs. real con datos conocidos (SC-003)

### Implementation for User Story 3

- [x] T015 [US3] `app/Services/Personal/DesempenoTecnicoService.php` — agregación sobre `detalle_ot`
  (spec 002) por técnico y rango de fechas (`resumen()`, `resumenPorTecnico()`), **aislado de la UI**
  (sin dependencias de Livewire) para que spec 007 lo reutilice
- [x] T016 [US3] Componente Volt `livewire/personal/desempeno.blade.php` (reporte con filtro de fechas)
- [x] T017 [US3] Ruta `/personal/desempeno` (`routes/web.php`); además, el placeholder "Resumen operativo"
  de la hoja de vida (FR-007) ahora muestra carga actual + desempeño reales del técnico

**Checkpoint**: Las 3 historias de usuario son funcionales de forma independiente

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T018 Verificar que las métricas de un técnico inactivado se conservan (no se filtran del histórico,
  solo de selectores de asignación — FR-006) — verificado a nivel de ficha (el registro `Tecnico` y su
  `tarifa_hora` sobreviven la inactivación); la verificación completa contra métricas de desempeño de OT
  queda pendiente junto con US3
- [X] T019 Ejecutar `php artisan test --filter=Personal` en verde (5/5)
- [X] T020 (añadida 2026-08-25, FR-008) Pantalla propia de gestión del catálogo de Especialidad
  (`personal.especialidades`, ruta `/especialidades`) — crear/editar/inactivar, sin afectar técnicos ya
  asignados; añadida migración `activo` a `especialidades`. Tests: `EspecialidadesTest.php` (5/5)

---

## Phase 7: Revisión 2026-09-01 - Sueldo historizado, valor día y hoja de vida (FR-009 a FR-013)

**Goal**: Costeo de mano de obra propia por día desde el sueldo mensual (historizado), datos laborales y
hoja de vida de solo lectura.

**Independent Test**: Crear un técnico con sueldo 2.400.000 → valor día `$ 80.000`; subirle el sueldo con
vigencia futura → se guarda una fila nueva, el sueldo vigente hoy no cambia; abrir su hoja de vida y ver
sueldo actual, valor día, datos laborales e histórico, todo de solo lectura.

- [x] T021 `config/personal.php` — `dias_mes` => 30 (env `PERSONAL_DIAS_MES`).
- [x] T022 Migración `create_sueldos_tecnico_table` (`tecnico_id` cascade, `sueldo` decimal(12,2),
  `vigente_desde` date, `registrado_por` FK users nullable, timestamps; índice `(tecnico_id, vigente_desde)`).
- [x] T023 Migración `add_datos_laborales_to_tecnicos_table` — agrega `fecha_ingreso` (date nullable),
  `cargo` (string(120) nullable), `tipo_contrato` (enum `termino_fijo`/`indefinido`/`prestacion_servicios`
  nullable) y **elimina** `tarifa_hora`.
- [x] T024 Modelo `app/Models/SueldoTecnico.php`; `Tecnico` +`sueldos()`, `sueldoVigente(?fecha)`,
  `valorDia(?fecha)`, accessors `sueldo_actual` / `valor_dia_actual`, `registrarSueldo($valor,$vigenteDesde,$por)`;
  quitar `tarifa_hora` de fillable/casts; agregar `fecha_ingreso`(date)/`cargo`/`tipo_contrato`.
- [x] T025 `TecnicoFactory` — quitar `tarifa_hora`; agregar datos laborales; state `conSueldo($valor)` que
  crea la fila en `sueldos_tecnico`.
- [x] T026 [FR-009/FR-010/FR-012] `admin/usuarios/index.blade.php` — en "Ficha de técnico": quitar "Tarifa
  por hora"; agregar Sueldo mensual + Vigente desde (default hoy) + valor día (solo lectura, `Moneda::cop`)
  + Fecha de ingreso + Cargo + Tipo de contrato. `guardar()`: upsert de `Tecnico` con los datos laborales
  y `registrarSueldo(...)` si el sueldo cambió.
- [x] T027 [FR-013] Hoja de vida — acción `verHojaVida($userId)` + panel de solo lectura en
  `admin/usuarios/index.blade.php` (datos, especialidad, estado, sueldo actual + valor día, datos
  laborales, tabla del histórico de sueldos, bloque "Resumen operativo" con aviso de dependencia de spec
  002). Botón "Hoja de vida" solo en filas de usuarios con rol Técnico.
- [x] T028 [P] Tests `tests/Feature/Personal/`: actualizar `TecnicoTest.php` (`tarifaHora`→sueldo/valor
  día); nuevo `SueldoTecnicoTest.php` (histórico: `sueldoVigente` por fecha, `valorDia = sueldo/30`, subir
  sueldo crea fila y no altera el vigente anterior); `HojaVidaTest.php` (panel visible solo para técnicos,
  de solo lectura, muestra histórico).
- [x] T029 Actualizar `README.md` (estado de clarify/plan/tasks de specs 004 y 002) y `php artisan test
  --filter=Personal` en verde.

**Checkpoint**: Contrato `Tecnico::valorDia($fecha)` listo y probado para que spec 002 lo use al costear
`detalle_ot.dias_trabajados`; hoja de vida operativa (sin la parte de carga/desempeño, que llega con 002).

---

## Dependencies & Execution Order

- **Setup + Foundational** bloquean todo.
- **US1** es el MVP real — sin ficha de técnico, spec 002 no puede asignar operarios reales.
- **US2** depende de que exista `detalle_ot` (spec 002) con datos, pero el método de conteo puede
  desarrollarse y testearse con datos de prueba antes de que spec 002 esté 100% completo.
- **US3** depende de historial real de OT para ser útil, pero es igualmente testeable con datos de prueba.

## Implementation Strategy

MVP = User Story 1 sola — es el bloqueante más urgente para spec 002 ("si no tengo el operario con su
especialidad no puedo crear una orden"). US2 y US3 pueden seguir en cualquier orden tras completar US1.
