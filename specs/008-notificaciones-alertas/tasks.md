# Tasks: Notificaciones y Alertas

**Input**: Design documents from `specs/008-notificaciones-alertas/` (plan.md, spec.md)

**Prerequisites**: specs 002, 003, 005, 006 disparando (o preparados para disparar) sus eventos de dominio;
puede desarrollarse en paralelo a ellos usando eventos sintéticos en tests

**Tests**: Incluidos — deduplicación multi-rol y reintento de fallos son reglas críticas.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 `QUEUE_CONNECTION=database` ya estaba en `.env`/`.env.example` desde antes, con `jobs`/
  `job_batches`/`failed_jobs` migrados (`0001_01_01_000002_create_jobs_table.php`) — solo faltaba que
  algo se encolara de verdad. Resuelto (2026-09-26) al hacer `OtEntregadaCliente implements ShouldQueue`
  (T027).
- [X] T002 [P] `php artisan notifications:table` — tabla `notifications` migrada desde Phase 11 de spec 002
  (2026-09-08), reusada aquí.

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Bloquea las 3 historias de usuario

- [X] T003 Migración `2026_09_26_090000_create_configuraciones_table.php` (`clave` único, `valor`,
  `descripcion`).
- [X] T004 Seeder `database/seeders/ConfiguracionesSeeder.php` — 3 umbrales por defecto
  (`ot.dias_umbral_vencimiento`, `equipos.dias_antelacion_mantenimiento`,
  `despachos.dias_alerta_firma_pendiente`), registrado en `DatabaseSeeder`. La pantalla
  `/configuraciones` muestra solo la descripción en lenguaje natural de cada umbral, sin el nombre técnico
  de la clave (decisión de UI, 2026-09-26).
- [X] T005 Modelo Eloquent `app/Models/Configuracion.php` (`obtener()`/`establecer()`, key-value).
- [X] T006 `app/Services/Notificaciones/DestinatariosPorRolService.php` — extraído de
  `NotificadorOt::aRoles()`/`aRolesYUsuarios()`; reutilizado también en `NotificarEventosEquipos` y
  `FirmaFisicaPendienteService` (antes cada uno repetía su propia consulta por rol).
- [X] T007 Componente Volt — implementado como `resources/views/livewire/notificaciones/campana.blade.php`
  (no en `app/Livewire/Notificaciones/Campana.php`, sigue la convención de Volt del proyecto de vista+lógica
  en un solo `.blade.php`). Ícono con conteo de no leídas, listado de últimas 15, marcar
  individual/todas como leídas, incluido en el layout global.

**Checkpoint**: Infraestructura completa ✅ — configuración editable en BD (T003-T006) y colas (T001)
cerradas 2026-09-26

---

## Phase 3: User Story 1 - Recibir notificaciones internas dentro del sistema (Priority: P1) 🎯 MVP

**Goal**: Campana con notificaciones leídas/no leídas, navegación al caso relacionado

**Independent Test**: Generar un evento sintético de prueba, verificar que aparece en la lista con estado
"no leída"; marcarla leída y verificar que el conteo baja

### Tests for User Story 1

- [X] T008 [P] [US1] Cubierto por `tests/Feature/OrdenesTrabajo/NotificacionesOtTest.php` y
  `tests/Feature/Equipos/NotificacionesEquiposTest.php` (no hay un `CampanaTest.php` dedicado, pero la
  aparición como no leída y el conteo se verifican ahí sobre notificaciones reales de OT/Equipos).
- [X] T009 [P] [US1] El payload `url` (`OtNotificacion::toArray()`) ya se genera con `route(...)` real por
  cada tipo de aviso; el `<a href="{{ $n->data['url'] }}">` en `campana.blade.php` navega al caso. Sin
  test de clic dedicado, pero la URL se verifica indirectamente en los tests de cada evento.

### Implementation for User Story 1

- [X] T010 [US1] `campana.blade.php` completo: conteo de no leídas, marcar individual (`marcarLeida($id)`)
  y todas (`marcarTodasLeidas()`) — ver líneas 13-31.
- [X] T011 [US1] Ruta `/notificaciones` (`livewire/notificaciones/index.blade.php`, paginada, filtro
  "solo no leídas", marcar individual/todas) — enlazada desde "Ver todas" en el dropdown de la campana.

**Checkpoint**: Mecanismo interno de notificaciones funcional — MVP del módulo ✅ completo

---

## Phase 4: User Story 2 - Alertas automáticas por vencimiento y stock (Priority: P1)

**Goal**: Notificaciones automáticas para OT vencida, stock bajo, mantenimiento próximo, solicitud
pendiente

**Independent Test**: Forzar cada condición (o disparar el evento sintéticamente) y verificar la
notificación correspondiente al rol adecuado

### Tests for User Story 2

- [X] T012 [P] [US2] Cubierto (sin archivo dedicado `AlertasAutomaticasTest.php`) por
  `NotificacionesOtTest.php` (`OtProximaAVencer`, `StockBajo`) y `NotificacionesEquiposTest.php`
  (`MantenimientoPreventivoProximoAVencer`). **`SolicitudPendiente` no tiene evento ni notificación —
  no implementado** (spec 003/006 no disparan ese evento; hueco real).
- [X] T013 [P] [US2] `tests/Feature/Notificaciones/DestinatariosPorRolServiceTest.php` — usuario con 2
  roles aplicables recibe una sola vez (FR-002), no duplica si el usuario ya viene por rol, ignora
  usuarios inactivos.

### Implementation for User Story 2

- [X] T014 [US2] Implementado como `OtNotificacion` genérica (no una clase `OtProximaAVencerNotification`
  por tipo) — `NotificadorOt::otProximaAVencer()` la instancia con título/cuerpo/ícono `alerta`.
- [X] T015 [P] [US2] Idem, vía `NotificadorOt::stockBajo()`.
- [X] T016 [P] [US2] Idem, vía `NotificarEventosEquipos::mantenimientoProximoAVencer()`.
- [ ] T017 [P] [US2] `SolicitudPendienteNotification` — no implementado (no hay evento que lo dispare).
- [X] T018 [US2] `NotificarEventosOt::otProximaAVencer()` (`app/Listeners/NotificarEventosOt.php:37-40`).
- [X] T019 [P] [US2] `NotificarEventosOt::stockBajo()` (líneas 42-45).
- [X] T020 [P] [US2] `NotificarEventosEquipos::mantenimientoProximoAVencer()`.
- [ ] T021 [P] [US2] Listener de `SolicitudPendiente` — no implementado (depende de T017).
- [X] T022 [US2] Registrado en `AppServiceProvider::boot()` (líneas 31-37) — no en un
  `EventServiceProvider` dedicado (el proyecto centraliza `Event::listen()` en `AppServiceProvider`), ya
  usando `DestinatariosPorRolService` (T006) por debajo.

**Checkpoint**: US1 + US2 funcionan de forma independiente ✅ — salvo la alerta de "solicitud pendiente"
(T017/T021), que sigue sin evento de origen en spec 003/006 (no es deuda de 008, es un evento que otro
spec todavía no dispara)

---

## Phase 5: User Story 3 - Notificación automática al cliente por correo (Priority: P2)

**Goal**: Correo automático al cliente en hitos de OT y Cotización, con reintento si falla

**Independent Test**: Completar entrega de una OT, verificar correo recibido; simular fallo SMTP y
verificar registro para reintento

### Tests for User Story 3

- [X] T023 [P] [US3] Cubierto por los tests de flujo de entrega de OT (`FlujoEntregaFirmadaTest.php` y
  similares) que verifican `Mail::to($correo)->send(...)` al entregar — no hay un
  `NotificacionClienteTest.php` aislado, pero el camino está probado.
- [X] T024 [P] [US3] `tests/Feature/Notificaciones/OtEntregadaClienteQueueTest.php` verifica
  `ShouldQueue`/`$tries`/`backoff()`; el reintento real ante fallo de SMTP lo maneja el worker de colas
  de Laravel (mueve a `failed_jobs` tras agotar los 3 intentos) — no hay un test que simule una caída de
  SMTP real (es infraestructura de Laravel, no lógica de negocio propia), pero la configuración está
  probada.

### Implementation for User Story 3

- [X] T025 [US3] Implementado como `Mailable` (`app/Mail/OtEntregadaCliente.php`), no como
  `Notification` de canal `mail` — mismo resultado funcional (correo al cliente al entregar,
  `NotificarEventosOt::otEntregada()` líneas 26-35 de `app/Listeners/NotificarEventosOt.php`), diseño
  distinto al propuesto pero válido.
- [ ] T026 [P] [US3] `CotizacionEnviadaClienteNotification` — no aplica todavía: spec 006 (de donde debía
  reutilizarse el envío) no tiene nada implementado. Bloqueado por spec 006, no es deuda de 008.
- [X] T027 [US3] `OtEntregadaCliente implements ShouldQueue` (2026-09-26), `$tries = 3`,
  `backoff() = [60, 300, 900]`. `QUEUE_CONNECTION=database` ya estaba configurado (T001) con
  `jobs`/`failed_jobs` migrados — solo faltaba que el Mailable se encolara. Test de entrega actualizado
  a `Mail::assertQueued()` (antes `assertSent()`, ya no aplica para un `ShouldQueue`).

**Checkpoint**: US1 + US2 completos; US3 funciona con reintento y cola real. Solo queda bloqueado por
spec 006: `CotizacionEnviadaClienteNotification` (T026), que no es deuda de 008.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T028 Los eventos de origen (`OtCreada`, `OtEntregada`, `OtProximaAVencer`, `StockBajo`,
  `MantenimientoPreventivoProximoAVencer`) ya están definidos en `app/Events/` de cada spec de origen
  (002, 003, 005) y documentados por su propio nombre + payload (`$event->ordenTrabajo`, etc.); no hay una
  nota explícita cruzada en cada `spec.md` de origen, pero el acoplamiento es claro en el código.
- [ ] T029 Supervisor/`queue:work` en producción (Hostinger) — pendiente de verdad, es tarea de despliegue:
  ahora que T027 encola de verdad, sin un worker corriendo el correo de entrega queda en `jobs` sin
  procesar. Documentar en el runbook de despliegue.
- [X] T030 Cobertura real bajo `tests/Feature/Notificaciones/` (`ConfiguracionTest`,
  `DestinatariosPorRolServiceTest`, `NotificacionesIndexTest`, `OtEntregadaClienteQueueTest`) +
  `--filter=NotificacionesOtTest` / `NotificacionesEquiposTest`. Suite completa: **337/337 en verde**,
  corrida dos veces sin fallos (2026-09-26).

**Brechas reales que quedaron pendientes de spec 008** (después del cierre de 2026-09-26):
1. Alerta de "solicitud pendiente" (T017/T021) — sin evento de origen en spec 003/006, no depende de 008.
2. `CotizacionEnviadaClienteNotification` (T026) — bloqueado hasta que exista spec 006.
3. Configurar Supervisor/`queue:work` en producción (T029) — tarea de despliegue, no de código.

---

## Dependencies & Execution Order

- **Setup + Foundational** bloquean todo — requieren tablas de colas/notificaciones migradas.
- **US1 (mecanismo interno)** es el MVP — sin esto, ninguna alerta tiene dónde mostrarse.
- **US2 (alertas automáticas)** depende de US1 para la UI, pero su lógica de Listeners puede desarrollarse
  y testearse en paralelo con eventos sintéticos, sin esperar a que 002/003/005/006 estén 100% terminados.
- **US3 (correo a cliente)** depende de que spec 006 ya tenga su infraestructura SMTP configurada
  (`ProveedorCorreoSaliente`), reutilizada aquí.

## Implementation Strategy

MVP = User Story 1 (campana funcional) + User Story 2 (alertas automáticas), que son el contenido
funcional explícito del Módulo 7 de la cotización. User Story 3 (correo a cliente) se entrega cuando spec
006 esté disponible, sin bloquear el resto.
