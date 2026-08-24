# Implementation Plan: Gestión de Equipos y Mantenimiento

**Branch**: `005-equipos-mantenimiento` | **Date**: 2026-08-24 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/005-equipos-mantenimiento/spec.md`

## Summary

CRUD de Equipos asociados a Cliente (spec 000), historial técnico reconstruido por consulta sobre las OT de
mantenimiento (spec 002), registro de variables técnicas en esquema clave-valor por intervención,
programación automática de mantenimiento preventivo con alertas (integradas con spec 008), y checklist
técnico digital de plantilla única. No introduce flujo de aprobación propio.

## Technical Context

**Language/Version**: PHP 8.2, Laravel 11 (^11.31)

**Primary Dependencies**: Livewire 3.6.4 + Volt 1.7, spatie/laravel-permission 6.9, Laravel Scheduler
(`schedule:run` diario para calcular próximos mantenimientos preventivos vencidos y disparar eventos hacia
spec 008)

**Storage**: MySQL/MariaDB — `equipos`, `variables_tecnicas`, `mantenimientos_preventivos`,
`checklist_mantenimiento`; el historial técnico en sí NO es una tabla propia, se reconstruye por consulta
sobre `ordenes_trabajo`/`detalle_ot` (spec 002) filtradas por `equipo_id`

**Testing**: Pest/PHPUnit — feature tests de registro de equipo, reconstrucción de historial (con OT de
prueba), registro de variables técnicas clave-valor, cálculo de próxima fecha de mantenimiento preventivo,
y checklist técnico bloqueante/no bloqueante según se decida en implementación

**Target Platform**: Servidor Linux (Hostinger VPS), aplicación Laravel monolítica

**Project Type**: Web application — monolito Laravel + Livewire/Volt

**Performance Goals**: Reconstrucción de historial técnico de un equipo en < 2s incluso con decenas de OT
asociadas — índice en `detalle_ot`/`ordenes_trabajo.equipo_id`

**Constraints**: Este módulo se implementa en Fase 3, después de que spec 002 (OT) ya esté operativo — su
historial depende de datos reales de OT de mantenimiento

**Scale/Scope**: Decenas a cientos de equipos por cliente; el `EquipoHistorialService` debe escalar sin
requerir N+1 queries por evidencia/checklist

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- ✅ **I. Stack Tecnológico Fijo**: Livewire/Volt, Laravel Scheduler nativo (sin paquete de colas externo).
- ✅ **II. Roles y Autorización**: registro de equipos disponible para Administrador y Jefe de Taller;
  consulta de historial disponible también para Técnico (solo lectura) vía policy.
- ✅ **III. Alcance Cerrado**: sin ampliaciones nuevas — las decisiones de clave-valor y plantilla única ya
  fueron aprobadas en `/speckit-clarify`.
- ✅ **IV. Trazabilidad**: el historial técnico es 100% derivado de eventos ya trazados en spec 002
  (evidencias, checklist, variables técnicas), no se duplica información.
- ✅ **V. Entrega Modular por Fases**: Fase 3, consistente con el cronograma; depende funcionalmente de
  spec 002 y spec 000 ya completos.

Sin violaciones. No se requiere Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/005-equipos-mantenimiento/
├── plan.md              # This file
└── spec.md              # Feature specification (ya existente)
```

### Source Code (repository root)

```text
app/
├── Models/
│   ├── Equipo.php
│   ├── VariableTecnica.php
│   ├── MantenimientoPreventivo.php       # periodicidad + próxima fecha por equipo
│   └── ChecklistMantenimiento.php        # plantilla única, ítems reutilizados por intervención
├── Services/
│   └── Equipos/
│       ├── EquipoHistorialService.php    # agrega OT + evidencias + variables técnicas + checklist
│       └── MantenimientoPreventivoService.php  # calcula próxima fecha, dispara evento de alerta
├── Livewire/
│   └── Equipos/
│       ├── Index.php                     # listado + registro de equipo
│       ├── Historial.php                 # ficha técnica del equipo (Success Criteria SC-001)
│       └── ChecklistMantenimiento.php    # completar checklist durante una OT de mantenimiento
├── Console/
│   └── Commands/
│       └── RevisarMantenimientosPreventivos.php  # programado diariamente vía Scheduler
└── Events/
    └── MantenimientoPreventivoProximoAVencer.php  # escuchado por spec 008

database/
└── migrations/
    ├── xxxx_create_equipos_table.php
    ├── xxxx_create_variables_tecnicas_table.php
    ├── xxxx_create_mantenimientos_preventivos_table.php
    └── xxxx_create_checklist_mantenimiento_table.php

routes/
└── web.php               # /equipos, /equipos/{equipo}/historial

tests/
└── Feature/
    └── Equipos/
        ├── RegistrarEquipoTest.php
        ├── HistorialTecnicoTest.php
        ├── VariableTecnicaTest.php
        ├── MantenimientoPreventivoTest.php
        └── ChecklistMantenimientoTest.php
```

**Structure Decision**: Monolito Laravel; se usa un `Event` (`MantenimientoPreventivoProximoAVencer`) en
vez de acoplar este módulo directamente a spec 008 — spec 008 se suscribe al evento, manteniendo la
dirección de dependencia limpia (005 no conoce a 008).

## Data Model

### Equipo (`equipos`)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| cliente_id | FK → `clientes` (spec 000) | |
| tipo | string(100) | |
| marca | string(100) nullable | |
| modelo | string(100) nullable | |
| serie | string(100) nullable | |
| ubicacion | string(150) nullable | |
| estado | string(50) nullable | Estado operativo del equipo (no confundir con estado de herramienta de spec 003) |
| periodicidad_mantenimiento_dias | int nullable | null = sin mantenimiento preventivo programado |
| timestamps | | |

### VariableTecnica (`variables_tecnicas`)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| ot_id | FK → `ordenes_trabajo` (spec 002) | Intervención asociada |
| equipo_id | FK → `equipos` | Denormalizado para consultas directas por equipo |
| nombre_variable | string(100) | Ej. "Temperatura" |
| valor | string(50) | |
| unidad | string(20) nullable | Ej. "°C" |

### MantenimientoPreventivo (`mantenimientos_preventivos`)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| equipo_id | FK → `equipos` | |
| ultima_fecha | date nullable | Fecha del último mantenimiento completado |
| proxima_fecha | date | Calculada: `ultima_fecha + periodicidad_mantenimiento_dias` |
| alerta_disparada | boolean default false | Evita disparar el mismo evento repetidamente |

### ChecklistMantenimiento (`checklist_mantenimiento`)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| ot_id | FK → `ordenes_trabajo` | |
| item | string | Plantilla única, ítems fijos en seeder |
| cumple | boolean nullable | |
| observaciones | string nullable | |

## Verificación end-to-end

1. Con un cliente ya registrado (spec 000), registrar un equipo con periodicidad de mantenimiento definida.
2. Con una OT de mantenimiento finalizada asociada al equipo (spec 002), registrar variables técnicas y
   completar el checklist técnico → verificar que `EquipoHistorialService` los expone en `/equipos/{equipo}/
   historial` junto con la evidencia de esa OT.
3. Ejecutar `php artisan RevisarMantenimientosPreventivos` (o esperar al scheduler) con una `proxima_fecha`
   vencida → verificar que se dispara `MantenimientoPreventivoProximoAVencer` (capturable en test con
   `Event::fake()`).
4. `php artisan test --filter=Equipos` en verde.
