# Implementation Plan: Gestión de Personal

**Branch**: `004-gestion-personal` | **Date**: 2026-08-24 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/004-gestion-personal/spec.md`

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
| tarifa_hora | decimal(10,2) nullable | Consumido por `CosteoOtService` (spec 002) |
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
