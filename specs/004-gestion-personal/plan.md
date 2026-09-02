# Implementation Plan: Gestión de Personal

**Branch**: `004-gestion-personal` | **Date**: 2026-08-24 · **Rev.**: 2026-09-01 (sueldo historizado + hoja de vida) | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/004-gestion-personal/spec.md`

> **Revisión 2026-09-01**: el costeo de mano de obra propia pasa de "tarifa por hora" a **día**
> (`valor_día = sueldo_mensual / 30`, divisor configurable). Se añade un **histórico de sueldos** por
> técnico (`sueldos_tecnico`) para que una OT cerrada no se recostee al cambiar el sueldo (FR-009..FR-011),
> **datos laborales** (fecha_ingreso, cargo, tipo_contrato) y una **hoja de vida** de solo lectura abierta
> desde la lista de Usuarios (FR-012, FR-013). Se elimina `tecnicos.tarifa_hora`. Detalle en la sección
> [Revisión 2026-09-01](#revisión-2026-09-01--sueldo-historizado-valor-día-y-hoja-de-vida) al final.

## Summary

Extiende a los usuarios con rol Técnico (spec 001) con una ficha de trabajador: especialidad (catálogo
fijo), estado activo/inactivo, tarifa/salario para el costeo de OT (spec 002), carga actual de tareas, e
indicadores de desempeño calculados sobre el historial de OT. No tiene flujo de aprobación propio — es
extensión de datos + reportes de lectura sobre datos de spec 002.

## Technical Context

**Language/Version**: PHP 8.2, Laravel 11 (^11.31)

**Primary Dependencies**: Livewire 3.6.4 + Volt 1.7, spatie/laravel-permission 6.9 (gestión restringida a
Administrador)

**Storage**: MySQL/MariaDB — `tecnicos`, `especialidades`; las métricas de desempeño se calculan por
consulta (agregación SQL sobre `detalle_ot`/`ordenes_trabajo` de spec 002), no se persisten como tabla
propia salvo que el volumen de datos lo justifique más adelante

**Testing**: Pest/PHPUnit — feature tests de registro de técnico, exclusión de inactivos en selectores de
OT, y tests de los cálculos de desempeño con datos de OT de prueba (comparativo estimado vs. real,
productividad)

**Target Platform**: Servidor Linux (Hostinger VPS), aplicación Laravel monolítica

**Project Type**: Web application — monolito Laravel + Livewire/Volt

**Performance Goals**: Reporte de desempeño por técnico en < 10s (SC-001 del spec) — usar agregación SQL
directa (no cargar todas las OT en PHP y sumar en memoria)

**Constraints**: Un Técnico (`tecnicos`) siempre depende de un `User` con rol Técnico ya existente (spec
001) — no se crean técnicos "huérfanos" sin cuenta de usuario

**Scale/Scope**: Decenas de técnicos; historial de OT creciente que alimenta las métricas de desempeño

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- ✅ **I. Stack Tecnológico Fijo**: Livewire/Volt, sin dependencias nuevas.
- ✅ **II. Roles y Autorización**: gestión de técnicos restringida a Administrador vía
  `TecnicoPolicy`/spatie-permission.
- ✅ **III. Alcance Cerrado**: sin ampliaciones — el catálogo fijo de especialidad ya fue decidido en
  `/speckit-clarify`.
- ✅ **IV. Trazabilidad**: histórico de OT (spec 002) es la fuente de verdad de desempeño; no se duplica ni
  se permite editar manualmente las métricas calculadas.
- ✅ **V. Entrega Modular por Fases**: la ficha de técnico (especialidad/activo) es demostrable de forma
  aislada; las métricas de desempeño requieren datos de spec 002 para ser útiles, pero no bloquean el
  desarrollo del módulo en sí (se pueden probar con datos de seed).

Sin violaciones. No se requiere Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/004-gestion-personal/
├── plan.md              # This file
└── spec.md              # Feature specification (ya existente)
```

### Source Code (repository root)

```text
app/
├── Models/
│   ├── Tecnico.php                       # belongsTo User, belongsTo Especialidad
│   └── Especialidad.php
├── Services/
│   └── Personal/
│       └── DesempenoTecnicoService.php   # agregaciones sobre detalle_ot/ordenes_trabajo (spec 002)
├── Livewire/
│   └── Personal/
│       ├── Tecnicos.php                  # listado + form (crear/editar/activo)
│       └── Desempeno.php                 # reporte por técnico, con filtro de fechas
└── Policies/
    └── TecnicoPolicy.php

database/
├── migrations/
│   ├── xxxx_create_especialidades_table.php
│   └── xxxx_create_tecnicos_table.php
└── seeders/
    └── EspecialidadesSeeder.php          # catálogo inicial (ej. Eléctrico, Mecánico, Refrigeración, ...)

routes/
└── web.php               # /personal/tecnicos, /personal/desempeno

tests/
└── Feature/
    └── Personal/
        ├── TecnicoTest.php
        └── DesempenoTecnicoTest.php
```

**Structure Decision**: Monolito Laravel; `DesempenoTecnicoService` se aísla porque también lo consume
spec 007 (Reportes/KPIs — "productividad del equipo"), evitando duplicar la lógica de agregación.

## Data Model

### Especialidad (`especialidades`) — catálogo fijo

| Campo | Tipo |
|---|---|
| id | bigint PK |
| nombre | string(100) unique |

### Tecnico (`tecnicos`)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| usuario_id | FK → `users` (spec 001) unique | Un usuario = a lo sumo un registro de técnico |
| especialidad_id | FK → `especialidades` | |
| ~~tarifa_hora~~ | — | **Eliminado en rev. 2026-09-01** (reemplazado por `sueldos_tecnico` + valor día) |
| fecha_ingreso | date nullable | Dato laboral (FR-012) |
| cargo | string(120) nullable | Dato laboral (FR-012) |
| tipo_contrato | enum('termino_fijo','indefinido','prestacion_servicios') nullable | Dato laboral (FR-012) |
| activo | boolean default true | |
| timestamps | | |

## Verificación end-to-end

1. `php artisan db:seed --class=EspecialidadesSeeder` siembra el catálogo de especialidades.
2. Crear un técnico vinculado a un usuario con rol Técnico existente (spec 001) → verificar que aparece en
   el selector de operario al crear una tarea de OT (spec 002, una vez integrado).
3. Inactivar un técnico → verificar que desaparece de los selectores de nuevas asignaciones pero sigue
   visible en el historial de OT ya ejecutadas.
4. Con datos de OT de prueba (seed), generar el reporte de desempeño de un técnico y verificar
   manualmente que el comparativo tiempo estimado vs. real y el conteo de OT son correctos.
5. `php artisan test --filter=Personal` en verde.

---

## Revisión 2026-09-01 — Sueldo historizado, valor día y hoja de vida

### Alcance

Costeo de mano de obra propia **por día** (`valor_día = sueldo_mensual / N`, `N = config('personal.dias_mes', 30)`),
con el sueldo **historizado** para que subir el sueldo no recostee OT ya cerradas (FR-009..FR-011); datos
laborales en la ficha (FR-012); hoja de vida de solo lectura desde la lista de Usuarios (FR-013). Elimina
`tecnicos.tarifa_hora`.

### Technical Context (incremental)

- Sin dependencias nuevas. Config nueva `config/personal.php` (`dias_mes` => 30).
- Montos (sueldo, valor día) formateados con `App\Support\Moneda` (spec 009, FR-001).
- El "sueldo vigente" es una consulta simple (`ORDER BY vigente_desde DESC LIMIT 1` con `vigente_desde <= fecha`);
  no se cachea.

### Data Model (incremental)

#### SueldoTecnico (`sueldos_tecnico`) — nueva

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| tecnico_id | FK → `tecnicos` cascade | |
| sueldo | decimal(12,2) | Sueldo mensual |
| vigente_desde | date | Fecha desde la que aplica este sueldo |
| registrado_por | FK → `users` nullable | Quién lo registró |
| timestamps | | |

Índice: `(tecnico_id, vigente_desde)`. Regla: `sueldoVigente($fecha)` = fila con mayor `vigente_desde <= $fecha`.

#### tecnicos — cambios

`tarifa_hora` se **elimina**. Se agregan `fecha_ingreso` (date), `cargo` (string), `tipo_contrato` (enum).

### Modelo / servicios

- `App\Models\SueldoTecnico` (fillable, cast `vigente_desde` date, `sueldo` decimal:2).
- `Tecnico`:
  - `sueldos(): HasMany` (orden `vigente_desde` desc).
  - `sueldoVigente(?CarbonInterface $fecha = null): ?float` — sueldo aplicable a `$fecha` (o hoy).
  - `valorDia(?CarbonInterface $fecha = null): ?float` — `round(sueldoVigente($fecha) / config('personal.dias_mes', 30), 2)`.
  - accessor `sueldo_actual` / `valor_dia_actual` para UI y listados.
  - `registrarSueldo(float $valor, CarbonInterface $vigenteDesde, ?User $por): SueldoTecnico` — inserta una
    fila nueva solo si el valor difiere del vigente a esa fecha.
- **spec 002** (cuando se implemente) usará `tecnico->valorDia($ot->fecha_finalizacion ?? now())` para costear
  `detalle_ot.dias_trabajados`. Este plan solo deja el contrato listo y probado en spec 004.

### UI

- **Formulario de Usuarios** (`admin/usuarios/index.blade.php`, sección "Ficha de técnico"): se quita
  "Tarifa por hora"; se agrega **Sueldo mensual** + **Vigente desde** (default hoy) + **valor día**
  (calculado, solo lectura), y **Fecha de ingreso**, **Cargo**, **Tipo de contrato**. Al guardar: si el
  sueldo difiere del vigente, `registrarSueldo(...)` inserta una fila nueva; nunca se edita el histórico.
- **Hoja de vida** (FR-013): botón "Hoja de vida" en la fila de cada usuario con rol Técnico → panel de
  solo lectura (mismo componente Volt `admin.usuarios.index`, estado `hojaVidaUserId`): datos personales,
  especialidad, estado, sueldo actual + valor día, datos laborales, tabla del histórico de sueldos, y el
  bloque "Resumen operativo" (carga + desempeño) que muestra un aviso "disponible al implementar Órdenes de
  Trabajo (spec 002)" hasta que exista `detalle_ot`.

### Verificación end-to-end (incremental)

1. Crear un usuario Técnico con especialidad, sueldo 2.400.000 y vigente-desde hoy → valor día = `$ 80.000`
   (2.400.000 / 30), formateado en COP.
2. Editar el técnico y subir el sueldo a 3.000.000 con vigencia mañana → se crea una 2ª fila en
   `sueldos_tecnico`; `sueldoVigente(hoy)` sigue devolviendo 2.400.000; `sueldoVigente(pasado mañana)` = 3.000.000.
3. Abrir la hoja de vida del técnico → muestra sueldo actual, valor día, datos laborales y las 2 filas del
   histórico; nada editable.
4. `php artisan test --filter=Personal` en verde.
