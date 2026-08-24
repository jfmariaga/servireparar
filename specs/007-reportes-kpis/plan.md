# Implementation Plan: Reportes e Indicadores (KPIs)

**Branch**: `007-reportes-kpis` | **Date**: 2026-08-24 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/007-reportes-kpis/spec.md`

## Summary

Módulo sin entidades propias: dashboards por rol y reportes agregados calculados por consulta directa
(sin caché ni polling, según clarificación) sobre datos de OT (spec 002), Inventario (spec 003), Personal
(spec 004) y Compras (spec 006). Primer corte de exportación (Excel/PDF) limitado a OT e Inventario;
Compras/Personal quedan para un corte posterior. Reutiliza `CosteoOtService` (spec 002) y
`DesempenoTecnicoService` (spec 004) en vez de reimplementar cálculos.

## Technical Context

**Language/Version**: PHP 8.2, Laravel 11 (^11.31)

**Primary Dependencies**: Livewire 3.6.4 + Volt 1.7 (dashboards), `maatwebsite/excel` (exportación Excel,
estándar de facto en Laravel), `barryvdh/laravel-dompdf` (exportación PDF, ya usado en spec 006 —
reutilizado aquí para no introducir una segunda librería de PDF)

**Storage**: Ninguna tabla propia — este módulo es 100% de lectura/agregación sobre tablas de otros
módulos (`ordenes_trabajo`, `detalle_ot`, `inventario`, `movimientos_inventario`, `tecnicos`, `compras`)

**Testing**: Pest/PHPUnit — feature tests de cada indicador con datos de prueba conocidos (comparando el
resultado del sistema contra el valor esperado calculado manualmente, ver SC-003), y tests de exportación
verificando que el archivo generado contiene exactamente el subconjunto filtrado

**Target Platform**: Servidor Linux (Hostinger VPS), aplicación Laravel monolítica

**Project Type**: Web application — monolito Laravel + Livewire/Volt

**Performance Goals**: Exportación filtrada en < 30s (SC-002); dashboards deben responder en tiempo
comparable a cualquier otra página del sistema (sin SLA distinto, al ser consulta directa según
Clarifications)

**Constraints**: Ningún indicador se cachea ni se actualiza en segundo plano — se recalcula en cada
carga/refresco (FR-008); esto simplifica el diseño a costa de no soportar "tiempo real" estricto, lo cual
ya fue aceptado explícitamente por el cliente

**Scale/Scope**: El volumen de datos agregados crece con el histórico de OT/inventario — usar índices ya
definidos en specs 002/003/004 (no se requieren índices adicionales específicos de este módulo salvo que
el profiling en `/speckit-tasks` lo justifique)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- ✅ **I. Stack Tecnológico Fijo**: Livewire/Volt; `maatwebsite/excel` y `laravel-dompdf` son utilidades de
  exportación puntuales, no alternativas al stack base.
- ✅ **II. Roles y Autorización**: cada dashboard filtra su contenido según el rol autenticado (Policy de
  "qué indicadores ve cada rol", no acceso binario a todo/nada).
- ✅ **III. Alcance Cerrado**: priorización de exportaciones (OT/Inventario primero) ya decidida en
  `/speckit-clarify`, respeta el alcance.
- ✅ **IV. Trazabilidad**: no aplica directamente (módulo de solo lectura), pero los datos que agrega ya
  son trazables en sus módulos de origen.
- ✅ **V. Entrega Modular por Fases**: depende explícitamente de que 002/003/004/006 tengan su modelo de
  datos definido (ver Assumptions del spec) — se implementa último dentro de Fase 3 por diseño.

Sin violaciones. No se requiere Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/007-reportes-kpis/
├── plan.md              # This file
└── spec.md              # Feature specification (ya existente)
```

### Source Code (repository root)

```text
app/
├── Services/
│   └── Reportes/
│       ├── DashboardAdministradorService.php   # cotizaciones activas/próximas a vencer, herramientas dañadas
│       ├── DashboardJefeTallerService.php      # OT activas/próximas a vencer/vencidas
│       ├── DashboardAlmacenistaService.php     # solicitudes pendientes, insumos por entregar, devoluciones
│       └── IndicadoresAgregadosService.php     # OT abiertas/cerradas/vencidas, cumplimiento de tiempos,
│                                                # productividad (delega en DesempenoTecnicoService, spec 004),
│                                                # estado de inventario
├── Exports/
│   ├── OrdenesTrabajoExport.php                # maatwebsite/excel
│   └── InventarioExport.php
├── Livewire/
│   └── Reportes/
│       ├── DashboardAdministrador.php
│       ├── DashboardJefeTaller.php
│       ├── DashboardAlmacenista.php
│       ├── IndicadoresAgregados.php
│       └── Exportador.php                      # filtros + botones Excel/PDF
└── Policies/
    └── ReportePolicy.php                       # qué dashboard/indicador ve cada rol

routes/
└── web.php               # / (dashboard según rol, redirección de FR-010 spec 001), /reportes/indicadores

tests/
└── Feature/
    └── Reportes/
        ├── DashboardPorRolTest.php
        ├── IndicadoresAgregadosTest.php         # valores conocidos vs. calculados
        └── ExportacionTest.php                  # Excel y PDF, con filtros
```

**Structure Decision**: Monolito Laravel; cada dashboard por rol es un Service independiente para que la
página de inicio (`/`) pueda componer solo el dashboard correspondiente al rol activo del usuario (según
`FR-010` de spec 001, prioridad Administrador > Jefe de Taller > Almacenista > Técnico), sin cargar
consultas de los otros roles.

## Data Model

Sin tablas propias. Fórmulas de los indicadores principales (documentadas aquí porque son el "contrato" de
este módulo, aunque no exista `data-model.md` con tablas):

```
OT abiertas    = COUNT(ordenes_trabajo WHERE estado NOT IN ('finalizada','entregada'))
OT cerradas    = COUNT(ordenes_trabajo WHERE estado IN ('finalizada','entregada'))
OT vencidas    = COUNT(ordenes_trabajo WHERE NOT terminal AND umbral_vencimiento superado)  [umbral: spec 002/CONFIGURACIONES]

cumplimiento_tiempos = COUNT(OT finalizadas dentro del tiempo estimado) / COUNT(OT finalizadas) × 100

estado_inventario = GROUP BY inventario.estado_herramienta / stock_actual < stock_minimo  [spec 003]

productividad_equipo = AVG(DesempenoTecnicoService::calcular($tecnico, $rango))  [spec 004, reutilizado]
```

## Verificación end-to-end

1. Con datos de prueba de OT en distintos estados (algunas vencidas, otras a tiempo), cargar el dashboard
   del Jefe de Taller y comparar manualmente los conteos mostrados contra una consulta SQL directa
   (SC-003).
2. Cargar el dashboard del Almacenista y del Administrador con datos de spec 003 y spec 006 respectivamente
   y verificar sus indicadores específicos.
3. Aplicar un filtro de fecha+estado al listado de OT y exportar a Excel y a PDF → verificar que el archivo
   contiene exactamente los registros filtrados en pantalla (User Story 3).
4. Refrescar la página del dashboard tras cambiar el estado de una OT en otra pestaña → verificar que el
   indicador se actualiza sin necesidad de acción adicional (consulta directa, FR-008).
5. `php artisan test --filter=Reportes` en verde.
