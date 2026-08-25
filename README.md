# Sistema de Gestión Operativa — SERVIREPARAR S.A.S

Proyecto planeado bajo metodología **Spec-Driven Development** ([GitHub Spec Kit](https://github.com/github/spec-kit)).
Esta etapa es de **planeación**: aún no hay código de aplicación, solo la constitución del proyecto y las
especificaciones funcionales de cada módulo.

## Documentos fuente

**Contractuales / diseño (aportados en la planeación inicial):**
- `Cotización 30-06-2026.pdf` — alcance contractual, cronograma, valor y forma de pago.
- `Mockups Servireparar.pdf` — diseño gráfico inicial de los flujos de Cotizaciones (Administrador),
  Órdenes de Trabajo (Jefe de Taller) y Solicitudes de Insumos (Almacenista).
- `ER.png` — modelo entidad-relación de referencia y cronograma del proyecto.

**Operativos reales (validación de campo, aportados posteriormente):**
- `Ordenes de Trabajo Servireparar.xlsx - DESMANTELAR ESCALERA.pdf` — formato real de OT en uso, con
  numeración `OTSV-`, costeo de mano de obra propia/contratista/repuestos y utilidad neta.
- `Proceso de Gestión de Órdenes de Trabajo (OT) – Taller SERVIREPARAR SAS.pdf` — proceso documentado
  (Cartagena, 2026-09-02) con responsables y flujo real de recepción a cierre de OT.
- `MANUAL DE ORGANIZACIÓN Y MAPA DE PROCESOS ALMACEN SERVIREPARAR.pdf` — codificación de ubicaciones,
  categorías de inventario, código de barras y KPIs reales de almacén (Cartagena, 2026-09-25).
- `INVENTARIO  SERVIREPARAR (1).xlsx` — inventario real por categoría (Llantas, EPP, Tuberías y Láminas,
  Insumos, Precios, Pinturas, Herramientas, Repuestos).

Estos documentos operativos reales llevaron a agregar el [spec 000](specs/000-catalogos-maestros/spec.md)
y a ampliar los specs 002 y 003 — ver detalle en cada spec y en la constitución (principio III).

## Stack tecnológico

PHP 8.2 · Laravel 11 (^11.31) · Livewire 3.6.4 + Volt 1.7 · Laravel Sanctum 4.0 ·
spatie/laravel-permission 6.9. Detalle completo de principios en
[`.specify/memory/constitution.md`](.specify/memory/constitution.md).

## Flujo de trabajo

Cada módulo pasa por: `/speckit-specify` → `/speckit-clarify` → `/speckit-plan` → `/speckit-tasks` →
`/speckit-implement` (skills instaladas en `.claude/skills/`). En esta etapa solo se completó `specify`
para todos los módulos; `clarify`/`plan`/`tasks` se abordan módulo por módulo en sesiones posteriores, una
vez validados los specs con el cliente.

## Mapeo Módulo ↔ Fase ↔ Hito de pago

| Spec | Módulo | Fase (cronograma) | Fechas | Hito de pago asociado |
|------|--------|--------------------|--------|------------------------|
| [000](specs/000-catalogos-maestros/spec.md) | Catálogos Maestros (Clientes, Proveedores, Contratistas) | Fase 1 — Inicio del Proyecto & Configuración | 13 Ago – 22 Ago 2026 | Hito 1 (40% · $7.000.000) — 13/08/2026 |
| [001](specs/001-autenticacion-usuarios/spec.md) | Autenticación y Gestión de Usuarios | Fase 1 — Inicio del Proyecto & Configuración | 13 Ago – 22 Ago 2026 | Hito 1 (40% · $7.000.000) — 13/08/2026 |
| [002](specs/002-ordenes-trabajo/spec.md) | Gestión de Órdenes de Trabajo (OT) | Fase 2 — Módulos Operativos Centrales | 23 Ago – 16 Sep 2026 | Hito 2 (30% · $5.250.000) — 16/09/2026 |
| [003](specs/003-inventario-bodega/spec.md) | Gestión de Inventario (Bodega) | Fase 2 | 23 Ago – 16 Sep 2026 | Hito 2 |
| [004](specs/004-gestion-personal/spec.md) | Gestión de Personal | Fase 2 | 23 Ago – 16 Sep 2026 | Hito 2 |
| [005](specs/005-equipos-mantenimiento/spec.md) | Gestión de Equipos y Mantenimiento | Fase 3 — Módulos Complementarios e Integraciones | 17 Sep – 01 Oct 2026 | Hito 3 (30% · $5.250.000) — 12/10/2026 |
| [006](specs/006-solicitudes-compra-cotizaciones/spec.md) | Solicitudes de Compra y Cotizaciones | Fase 3 | 17 Sep – 01 Oct 2026 | Hito 3 |
| [007](specs/007-reportes-kpis/spec.md) | Reportes e Indicadores (KPIs) | Fase 3 | 17 Sep – 01 Oct 2026 | Hito 3 |
| [008](specs/008-notificaciones-alertas/spec.md) | Notificaciones y Alertas | Fase 3 | 17 Sep – 01 Oct 2026 | Hito 3 |
| — | Pruebas, Capacitación y Entrega Final | Fase 4 | 02 Oct – 12 Oct 2026 | Hito 3 (cierre) |

Duración total: 60 días calendario (13/08/2026 – 12/10/2026). Soporte y garantía: 2 meses posteriores a la
entrega (hasta 12/12/2026).

## Estado de clarificación por spec

| Spec | Estado `/speckit-clarify` | Notas |
|------|---------------------------|-------|
| 000 — Catálogos Maestros | ✅ Completado (2026-08-24) | Nuevo spec agregado tras validar documentos operativos reales. Contratistas como entidad propia, distinta de Proveedores. Listo para `/speckit-plan`. |
| 001 — Autenticación y Usuarios | ✅ Completado (2026-08-24) | Sin auto-registro público, redirección por prioridad de rol, throttle estándar de Laravel. Listo para `/speckit-plan`. |
| 002 — Órdenes de Trabajo | ✅ Completado (2026-08-24) | Umbral de vencimiento configurable; rechazo de salida de equipo reabre la OT a "En curso"; ampliado con numeración `OTSV-`, mano de obra de contratistas y costeo/utilidad neta por OT (ver constitución, principio III). Listo para `/speckit-plan`. |
| 003 — Inventario (Bodega) | ✅ Completado (2026-08-24) | Auditorías bajo demanda; ajustes requieren aprobación del Administrador; ampliado con las 7 categorías reales, ubicación física Pasillo-Estante-Nivel y códigos de barra Code128. Listo para `/speckit-plan`. |
| 004 — Gestión de Personal | ✅ Completado (2026-08-24) | Especialidad de técnico como catálogo fijo predefinido. Listo para `/speckit-plan`. |
| 005 — Equipos y Mantenimiento | ✅ Completado (2026-08-24) | Variables técnicas como esquema clave-valor; checklist técnico con plantilla única genérica; registro de Cliente reubicado al spec 000. Listo para `/speckit-plan`. |
| 006 — Compras y Cotizaciones | ✅ Completado (2026-08-24) | Recepción vía IMAP polling, correos fuera de hilo notifican al Administrador, "Cotización" como entidad propia; registro de Proveedor reubicado al spec 000. Queda abierto (no bloqueante): matcheo automático de cliente remitente. |
| 007 — Reportes e Indicadores | ✅ Completado (2026-08-24) | Dashboard por recarga/refresco (sin polling); exportaciones de OT e Inventario primero, Compras/Personal en corte posterior. Listo para `/speckit-plan`. |
| 008 — Notificaciones y Alertas | ✅ Sin bloqueantes | No tenía `[NEEDS CLARIFICATION]` propios; dependía de 002/005 (umbrales) y 006 (canal de correo), ya resueltos. Listo para `/speckit-plan`. |

Los 9 specs (000 a 008) quedaron clarificados. El único punto abierto no bloqueante restante es el matcheo
automático de cliente remitente en el spec 006.

## Estado de `/speckit-plan`

| Spec | Estado `/speckit-plan` |
|------|-------------------------|
| [000 — Catálogos Maestros](specs/000-catalogos-maestros/plan.md) | ✅ Completado (2026-08-24) |
| [001 — Autenticación y Usuarios](specs/001-autenticacion-usuarios/plan.md) | ✅ Completado (2026-08-24) |
| [002 — Órdenes de Trabajo](specs/002-ordenes-trabajo/plan.md) | ✅ Completado (2026-08-24) |
| [003 — Inventario (Bodega)](specs/003-inventario-bodega/plan.md) | ✅ Completado (2026-08-24) |
| [004 — Gestión de Personal](specs/004-gestion-personal/plan.md) | ✅ Completado (2026-08-24) |
| [005 — Equipos y Mantenimiento](specs/005-equipos-mantenimiento/plan.md) | ✅ Completado (2026-08-24) |
| [006 — Compras y Cotizaciones](specs/006-solicitudes-compra-cotizaciones/plan.md) | ✅ Completado (2026-08-24) |
| [007 — Reportes e Indicadores](specs/007-reportes-kpis/plan.md) | ✅ Completado (2026-08-24) |
| [008 — Notificaciones y Alertas](specs/008-notificaciones-alertas/plan.md) | ✅ Completado (2026-08-24) |

Los 9 planes técnicos (000-008) están completos. Decisiones de diseño transversales que atraviesan varios
módulos:

- **Servicios reutilizables**: `CosteoOtService` (002) y `DesempenoTecnicoService` (004) se aíslan de la UI
  para que spec 007 los reutilice sin duplicar lógica de cálculo.
- **Desacoplamiento por eventos**: 002 (OT), 003 (Inventario) y 005 (Equipos) disparan eventos de dominio
  propios (`OtProximaAVencer`, stock bajo, mantenimiento preventivo) sin conocer a spec 008 — éste se
  suscribe vía `Listeners`, manteniendo la dependencia en una sola dirección.
- **Correo desacoplado del protocolo**: spec 006 define `ProveedorCorreoEntrante`/`ProveedorCorreoSaliente`
  como interfaces (implementadas con IMAP polling + SMTP), permitiendo testear con fakes y cambiar de
  proveedor sin tocar la lógica de negocio de Cotizaciones.
- **Librerías añadidas** (todas como utilidad puntual detrás de una interfaz o wrapper propio, no como
  reemplazo del stack base): `picqer/php-barcode-generator` (003, códigos Code128 sin dependencias
  nativas), `webklex/php-imap` (006, sin requerir `ext-imap`), `barryvdh/laravel-dompdf` y
  `maatwebsite/excel` (006/007, exportaciones).

## Estado de `/speckit-tasks`

| Spec | Estado `/speckit-tasks` | MVP (primer incremento) |
|------|--------------------------|---------------------------|
| [000 — Catálogos Maestros](specs/000-catalogos-maestros/tasks.md) | ✅ Completado (2026-08-24) | User Story 1 (Cliente) |
| [001 — Autenticación y Usuarios](specs/001-autenticacion-usuarios/tasks.md) | ✅ Completado (2026-08-24) | US1 (Login) + US4 (Admin usuarios) |
| [002 — Órdenes de Trabajo](specs/002-ordenes-trabajo/tasks.md) | ✅ Completado (2026-08-24) | US1 + US2 (crear y ejecutar OT) |
| [003 — Inventario (Bodega)](specs/003-inventario-bodega/tasks.md) | ✅ Completado (2026-08-24) | US1 (solicitudes desde OT) |
| [004 — Gestión de Personal](specs/004-gestion-personal/tasks.md) | ✅ Completado (2026-08-24) | US1 (ficha de técnico) |
| [005 — Equipos y Mantenimiento](specs/005-equipos-mantenimiento/tasks.md) | ✅ Completado (2026-08-24) | US1 (registro de equipos) |
| [006 — Compras y Cotizaciones](specs/006-solicitudes-compra-cotizaciones/tasks.md) | ✅ Completado (2026-08-24) | US1 + US2 (recepción + construcción/envío) |
| [007 — Reportes e Indicadores](specs/007-reportes-kpis/tasks.md) | ✅ Completado (2026-08-24) | US1 (dashboards por rol) |
| [008 — Notificaciones y Alertas](specs/008-notificaciones-alertas/tasks.md) | ✅ Completado (2026-08-24) | US1 + US2 (campana + alertas automáticas) |

Los 9 módulos tienen tareas ejecutables organizadas por historia de usuario, con tests-first, checkpoints
de independencia y orden de dependencias explícito en cada `tasks.md`. El ciclo completo de Spec-Driven
Development (`/speckit-specify` → `/speckit-clarify` → `/speckit-plan` → `/speckit-tasks`) está cerrado
para los 9 specs — el proyecto está listo para `/speckit-implement`.

**Entorno de desarrollo local**: Laragon (Windows) con MySQL — mismo motor que producción (Hostinger), sin
SQLite ni en tests, para evitar divergencias de comportamiento (ver constitución v1.2.0).

Orden de implementación recomendado (respeta dependencias reales, no solo el orden de fases comercial):
**000 → 001 → 002/003/004 (paralelizable entre sí tras 000+001) → 005/006 (paralelizable tras 000+002/
003/004) → 007 (requiere 002/003/004/006) → 008 (requiere eventos de 002/003/005/006, pero su mecanismo
interno puede desarrollarse en paralelo desde el inicio)**.
