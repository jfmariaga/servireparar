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

## Puntos abiertos a resolver antes de `/speckit-plan`

Cada spec deja explícitos sus `[NEEDS CLARIFICATION]`. Los más críticos, por bloquear diseño técnico de
otros módulos:

- **Spec 006**: mecanismo técnico de integración de correo (IMAP / Gmail API / webhook transaccional) —
  condiciona también la spec 008 (notificaciones al cliente) y el modelo de datos de "Caso de Cotización",
  que no está modelado de forma independiente en el ER original.
- **Spec 002 / 005**: umbrales exactos de alertas de vencimiento (OT y mantenimiento preventivo) —
  condicionan spec 008.
- **Spec 001**: alcance del auto-registro público de usuarios visto en el mockup (Ilustración 2).
