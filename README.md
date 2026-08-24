# SERVIOPS — Sistema de Gestión Operativa SERVIREPARAR S.A.S

Proyecto planeado bajo metodología **Spec-Driven Development** ([GitHub Spec Kit](https://github.com/github/spec-kit)).
Esta etapa es de **planeación**: aún no hay código de aplicación, solo la constitución del proyecto y las
especificaciones funcionales de cada módulo.

## Documentos fuente

- `Cotización 30-06-2026.pdf` — alcance contractual, cronograma, valor y forma de pago.
- `Mockups Servireparar.pdf` — diseño gráfico inicial de los flujos de Cotizaciones (Administrador),
  Órdenes de Trabajo (Jefe de Taller) y Solicitudes de Insumos (Almacenista).
- `ER.png` — modelo entidad-relación de referencia y cronograma del proyecto.

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
| 001 — Autenticación y Usuarios | ✅ Completado (2026-08-24) | Sin auto-registro público, redirección por prioridad de rol, throttle estándar de Laravel. Listo para `/speckit-plan`. |
| 002 — Órdenes de Trabajo | ✅ Completado (2026-08-24) | Umbral de vencimiento configurable por el Administrador; rechazo de salida de equipo reabre la OT a "En curso". Listo para `/speckit-plan`. |
| 003 — Inventario (Bodega) | ✅ Completado (2026-08-24) | Auditorías bajo demanda (sin periodicidad fija); ajustes de auditoría requieren aprobación del Administrador. Listo para `/speckit-plan`. |
| 004 — Gestión de Personal | ✅ Completado (2026-08-24) | Especialidad de técnico como catálogo fijo predefinido. Listo para `/speckit-plan`. |
| 005 — Equipos y Mantenimiento | ✅ Completado (2026-08-24) | Variables técnicas como esquema clave-valor; checklist técnico con plantilla única genérica. Listo para `/speckit-plan`. |
| 006 — Compras y Cotizaciones | ✅ Completado (2026-08-24) | Recepción vía IMAP polling, correos fuera de hilo notifican al Administrador, "Cotización" como entidad propia (no reutiliza OT). Queda abierto (no bloqueante): matcheo automático de cliente remitente. |
| 007 — Reportes e Indicadores | ✅ Completado (2026-08-24) | Dashboard por recarga/refresco (sin polling); exportaciones de OT e Inventario primero, Compras/Personal en corte posterior. Listo para `/speckit-plan`. |
| 008 — Notificaciones y Alertas | ✅ Sin bloqueantes | No tenía `[NEEDS CLARIFICATION]` propios; dependía de 002/005 (umbrales) y 006 (canal de correo), ya resueltos. Listo para `/speckit-plan`. |

Los 8 specs quedaron clarificados y listos para pasar a `/speckit-plan`. El único punto abierto no
bloqueante restante es el matcheo automático de cliente remitente en el spec 006, que puede resolverse
durante el diseño técnico de ese módulo. Recomendado iniciar `/speckit-plan` siguiendo el orden del
cronograma: 001 → 002/003/004 (Fase 2) → 005/006/007/008 (Fase 3).
