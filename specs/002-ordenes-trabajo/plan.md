# Implementation Plan: Gestión de Órdenes de Trabajo (OT)

**Branch**: `002-ordenes-trabajo` | **Date**: 2026-08-24 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/002-ordenes-trabajo/spec.md`

## Summary

Módulo central del sistema: CRUD de OT con máquina de estados derivada del avance de sus tareas, checklist
de cierre obligatorio, flujo de entrega con aprobación administrativa, integración con Inventario (spec
003) para solicitudes de insumos, y cálculo automático de costeo/utilidad neta por OT (mano de obra propia +
contratista + repuestos vs. valor cobrado). Depende de spec 000 (Cliente, Contratista) y spec 004
(Técnico) ya operativos.

## Technical Context

**Language/Version**: PHP 8.2, Laravel 11 (^11.31)

**Primary Dependencies**: Livewire 3.6.4 + Volt 1.7 (formulario de OT multi-sección, tablero con filtros),
spatie/laravel-permission 6.9 (policy de aprobación de salida de equipo, solo Administrador), Laravel
Queues (recalculo de estado y notificaciones desacoplados de la request), Laravel Storage (evidencias
fotográficas de entrada/salida)

**Storage**: MySQL/MariaDB — `ordenes_trabajo`, `detalle_ot` (tareas), `ot_mano_obra_contratista` (nueva),
`evidencias_ot`, `checklist_ot`, `estados_ot`, `prioridades`; archivos de evidencia en `storage/app/public`
(o S3-compatible si Hostinger lo soporta, a definir en despliegue)

**Testing**: Pest/PHPUnit — feature tests por historia de usuario (creación con validación de tarea+técnico
obligatorios, transición automática de estado, checklist bloqueante, flujo de aprobación de salida,
cálculo de costeo con casos de prueba tomados del formato Excel real como oráculo)

**Target Platform**: Servidor Linux (Hostinger VPS), aplicación Laravel monolítica

**Project Type**: Web application — monolito Laravel + Livewire/Volt

**Performance Goals**: Tablero de OT con filtros debe responder en < 10s percibidos incluso con cientos de
OT históricas (SC-004) — usar paginación + índices en `estado_id`, `cliente_id`, `fecha_creacion`

**Constraints**: El estado de la OT NUNCA se edita manualmente salvo corrección administrativa auditada
(FR-004) — la transición de estado vive en un Observer/Service, no en los formularios

**Scale/Scope**: Decenas de OT simultáneas activas, historial creciente indefinidamente; numeración
consecutiva `OTSV-00001` sin reinicio anual (a confirmar en `/speckit-clarify` si el cliente lo requiere)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- ✅ **I. Stack Tecnológico Fijo**: Livewire/Volt full-stack, sin API REST separada.
- ✅ **II. Roles y Autorización**: aprobación de salida de equipo restringida a Administrador vía
  `OrdenTrabajoPolicy@approveEquipmentExit`, usando spatie-permission.
- ⚠️ **III. Alcance Cerrado**: User Story 5 (costeo/utilidad) es una ampliación de alcance — **ya
  aprobada y documentada explícitamente** en la constitución v1.1.0 (principio III, excepción 2026-08-24).
  No requiere nueva justificación aquí, pero se referencia para trazabilidad.
- ✅ **IV. Trazabilidad**: cambios de estado, correcciones administrativas y rechazos de salida quedan
  como eventos en una tabla `ot_eventos`/comentarios (ver Data Model), no se sobrescriben.
- ✅ **V. Entrega Modular por Fases**: demostrable de forma aislada al final de Fase 2, aunque el costeo
  de contratistas depende de que spec 000 (Contratista) ya exista.

Sin violaciones nuevas fuera de la excepción ya aprobada. No se requiere Complexity Tracking adicional.

## Project Structure

### Documentation (this feature)

```text
specs/002-ordenes-trabajo/
├── plan.md              # This file
└── spec.md              # Feature specification (ya existente)
```

### Source Code (repository root)

```text
app/
├── Models/
│   ├── OrdenTrabajo.php
│   ├── DetalleOt.php                     # tarea de la OT
│   ├── OtManoObraContratista.php         # nueva tabla intermedia
│   ├── EvidenciaOt.php
│   ├── ChecklistOt.php
│   ├── EstadoOt.php
│   └── Prioridad.php
├── Services/
│   └── OrdenTrabajo/
│       ├── EstadoOtService.php           # centraliza la máquina de estados (FR-004)
│       ├── CosteoOtService.php           # calcula costo total y utilidad neta (User Story 5)
│       └── SolicitudInsumoService.php    # genera solicitud hacia spec 003 al guardar una tarea con insumo
├── Livewire/
│   └── OrdenesTrabajo/
│       ├── Tablero.php                   # listado + filtros (Ilustración 16)
│       ├── Crear.php                     # formulario multi-sección (Ilustración 13)
│       ├── Detalle.php                   # gestión de tareas, evidencias, checklist, comentarios (Ilustración 15)
│       └── Costeo.php                    # vista de costo/utilidad, visible solo Administrador
└── Policies/
    └── OrdenTrabajoPolicy.php            # create, update, approveEquipmentExit, viewCosteo

database/
└── migrations/
    ├── xxxx_create_prioridades_table.php
    ├── xxxx_create_estados_ot_table.php
    ├── xxxx_create_ordenes_trabajo_table.php
    ├── xxxx_create_detalle_ot_table.php
    ├── xxxx_create_ot_mano_obra_contratista_table.php
    ├── xxxx_create_evidencias_ot_table.php
    ├── xxxx_create_checklist_ot_table.php
    └── xxxx_create_ot_eventos_table.php   # trazabilidad de correcciones/rechazos

routes/
└── web.php               # /ordenes-trabajo, /ordenes-trabajo/crear, /ordenes-trabajo/{ot}

tests/
└── Feature/
    └── OrdenesTrabajo/
        ├── CrearOtTest.php
        ├── FlujoEstadoTest.php
        ├── ChecklistCierreTest.php
        ├── EntregaEquipoTest.php
        ├── CorreccionOtTest.php
        └── CosteoUtilidadTest.php         # casos tomados del Excel real como oráculo
```

**Structure Decision**: Monolito Laravel; la lógica de máquina de estados y costeo se aísla en `app/
Services/OrdenTrabajo/` (no en los Livewire components) para poder testearla independientemente de la UI y
reutilizarla desde spec 007 (Reportes/KPIs), que consume estos mismos cálculos.

## Data Model

### OrdenTrabajo (`ordenes_trabajo`)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| numero_ot | string(20) unique | Formato `OTSV-00001`, generado por `EstadoOtService` (o un `OtNumberGenerator`) |
| cliente_id | FK → `clientes` (spec 000) | Obligatorio |
| equipo_id | FK → `equipos` (spec 005) nullable | 0..1 por OT |
| prioridad_id | FK → `prioridades` | |
| estado_id | FK → `estados_ot` | Solo modificado por `EstadoOtService` |
| tipo_servicio | enum('taller','domicilio') | |
| descripcion | text | |
| tiempo_estimado_dias | decimal | Estimación en días; base para el umbral de vencimiento (spec 008, `CONFIGURACIONES`) |
| fecha_creacion | timestamp | |
| fecha_finalizacion | timestamp nullable | |
| valor_proyecto | decimal(12,2) nullable default null | Valor cobrado al cliente; puede quedar sin definir (ver Edge Cases del spec) |
| equipo_estado_ingreso | string(255) nullable | Descripción del estado del equipo al recibirlo |
| firma_cliente_url | string nullable | Evidencia de conformidad al cerrar (opcional según spec) |
| observaciones | text nullable | |
| timestamps | | |

### DetalleOt (`detalle_ot` — tareas)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| ot_id | FK → `ordenes_trabajo` | |
| descripcion | text | |
| tecnico_id | FK → `tecnicos` (spec 004) | Operario responsable |
| insumo_id | FK → `inventario` (spec 003) nullable | Si la tarea requiere insumo |
| cantidad_insumo | decimal nullable | |
| estado_tarea | enum('pendiente','en_curso','finalizada') | Alimenta `EstadoOtService` |
| fecha_inicio | timestamp nullable | |
| fecha_fin | timestamp nullable | |
| dias_trabajados | decimal nullable | Días trabajados por el técnico en la tarea; base del costeo de mano de obra propia (× valor día del técnico, spec 004) |

### OtManoObraContratista (`ot_mano_obra_contratista`) — nueva

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| ot_id | FK → `ordenes_trabajo` | |
| contratista_id | FK → `contratistas` (spec 000) | |
| especialidad | string(100) nullable | Puede diferir de la especialidad general del contratista |
| cantidad | decimal | |
| valor | decimal(12,2) | |

### EvidenciaOt (`evidencias_ot`)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| ot_id | FK | |
| tipo_archivo | string | |
| url_archivo | string | |
| tipo_registro | enum('entrada','salida','proceso') | Nuevo, según proceso real documentado |
| descripcion | string nullable | |
| fecha_subida | timestamp | |

### ChecklistOt (`checklist_ot`)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| ot_id | FK | |
| item | string | |
| cumple | boolean nullable | null = pendiente |
| observaciones | string nullable | |

### EstadoOt / Prioridad — catálogos simples (id, nombre, es_terminal / nivel)

### OtEvento (`ot_eventos`) — trazabilidad, nueva

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| ot_id | FK | |
| usuario_id | FK → `users` | Quién ejecutó la acción |
| tipo | enum('correccion','rechazo_salida','cambio_estado', ...) | |
| descripcion | text | |
| created_at | timestamp | |

### Cálculo de costeo (`CosteoOtService`, no persistido como tabla — calculado on-demand o cacheado)

```
costo_mano_obra_propia = Σ (dias_trabajados × valor_dia_tecnico)
                         [valor_dia = sueldo_mensual / 30 del sueldo del técnico VIGENTE a la fecha de
                          referencia de la OT (fecha_finalizacion, o hoy si sigue abierta); histórico de
                          sueldos en spec 004 (FR-009..FR-011) — una OT cerrada no se recostea]
costo_contratistas      = Σ ot_mano_obra_contratista.valor
costo_repuestos         = Σ (detalle_ot.cantidad_insumo × inventario.costo_unitario)  [spec 003]
costo_total             = costo_mano_obra_propia + costo_contratistas + costo_repuestos
utilidad_neta           = valor_proyecto - costo_total
```

## Verificación end-to-end

1. Con spec 000 y 004 ya poblados (cliente, técnico con especialidad), crear una OT desde `/ordenes-
   trabajo/crear` con una tarea y técnico asignado — debe rechazar el guardado si no hay tarea (FR-002).
2. Iniciar la tarea → verificar transición automática a "En curso"; completar checklist y finalizar tarea →
   verificar transición a "Finalizada" solo si el checklist está 100% completo (SC-003).
3. Solicitar salida de equipo, rechazar como Administrador, verificar que vuelve a "En curso" con el
   comentario de rechazo visible (User Story 3, escenario 4).
4. Agregar un contratista con valor y repuestos con costo, definir `valor_proyecto`, y comparar el
   costo/utilidad calculado contra el mismo caso resuelto manualmente en el Excel real (SC-005, 0%
   desviación).
5. `php artisan test --filter=OrdenesTrabajo` en verde, incluyendo `CosteoUtilidadTest`.
