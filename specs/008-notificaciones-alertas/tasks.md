# Tasks: Notificaciones y Alertas

**Input**: Design documents from `specs/008-notificaciones-alertas/` (plan.md, spec.md)

**Prerequisites**: specs 002, 003, 005, 006 disparando (o preparados para disparar) sus eventos de dominio;
puede desarrollarse en paralelo a ellos usando eventos sintéticos en tests

**Tests**: Incluidos — deduplicación multi-rol y reintento de fallos son reglas críticas.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup (Shared Infrastructure)

- [ ] T001 Configurar `QUEUE_CONNECTION=database` en `.env` (local Laragon) y ejecutar
  `php artisan queue:table && php artisan queue:migrate`
- [ ] T002 [P] `php artisan notifications:table` (tabla nativa `notifications`)

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Bloquea las 3 historias de usuario

- [ ] T003 Migración `xxxx_create_configuraciones_table.php`
- [ ] T004 Seeder `database/seeders/ConfiguracionesSeeder.php` (valores por defecto de umbrales:
  `ot.umbral_vencimiento_horas`, `mail.remitente_default`, etc.)
- [ ] T005 Modelo Eloquent `Configuracion`
- [ ] T006 `app/Services/Notificaciones/DestinatariosPorRolService.php` (resuelve destinatarios evitando
  duplicados multi-rol — FR-002)
- [ ] T007 Componente Volt `app/Livewire/Notificaciones/Campana.php` (ícono + listado + marcar leída,
  reutilizado en el layout global)

**Checkpoint**: Infraestructura de notificaciones lista — las historias de usuario pueden implementarse

---

## Phase 3: User Story 1 - Recibir notificaciones internas dentro del sistema (Priority: P1) 🎯 MVP

**Goal**: Campana con notificaciones leídas/no leídas, navegación al caso relacionado

**Independent Test**: Generar un evento sintético de prueba, verificar que aparece en la lista con estado
"no leída"; marcarla leída y verificar que el conteo baja

### Tests for User Story 1

- [ ] T008 [P] [US1] Feature test `tests/Feature/Notificaciones/CampanaTest.php`: notificación creada
  aparece como no leída; marcar leída actualiza `read_at`
- [ ] T009 [P] [US1] Feature test: clic en notificación navega al caso relacionado (usa `data.url` del
  payload)

### Implementation for User Story 1

- [ ] T010 [US1] Completar `Campana.php` (conteo de pendientes, marcar individual/todas como leídas)
- [ ] T011 [US1] Ruta opcional `/notificaciones` (listado completo más allá de la campana)

**Checkpoint**: Mecanismo interno de notificaciones funcional — MVP del módulo

---

## Phase 4: User Story 2 - Alertas automáticas por vencimiento y stock (Priority: P1)

**Goal**: Notificaciones automáticas para OT vencida, stock bajo, mantenimiento próximo, solicitud
pendiente

**Independent Test**: Forzar cada condición (o disparar el evento sintéticamente) y verificar la
notificación correspondiente al rol adecuado

### Tests for User Story 2

- [ ] T012 [P] [US2] Feature test `tests/Feature/Notificaciones/AlertasAutomaticasTest.php`: un caso por
  tipo de evento (`OtProximaAVencer`, `StockBajo`, `MantenimientoPreventivoProximoAVencer`,
  `SolicitudPendiente`)
- [ ] T013 [P] [US2] Feature test `tests/Feature/Notificaciones/DeduplicacionMultiRolTest.php`: usuario con
  2 roles aplicables recibe una sola notificación (FR-002)

### Implementation for User Story 2

- [ ] T014 [US2] `app/Notifications/OtProximaAVencerNotification.php` (canal `database`)
- [ ] T015 [P] [US2] `app/Notifications/StockBajoNotification.php`
- [ ] T016 [P] [US2] `app/Notifications/MantenimientoPreventivoNotification.php`
- [ ] T017 [P] [US2] `app/Notifications/SolicitudPendienteNotification.php`
- [ ] T018 [US2] `app/Listeners/NotificarOtProximaAVencer.php` (escucha evento de spec 002)
- [ ] T019 [P] [US2] `app/Listeners/NotificarStockBajo.php` (escucha evento de spec 003)
- [ ] T020 [P] [US2] `app/Listeners/NotificarMantenimientoPreventivo.php` (escucha evento de spec 005)
- [ ] T021 [P] [US2] `app/Listeners/NotificarSolicitudPendiente.php` (escucha eventos de spec 003/006)
- [ ] T022 [US2] Registrar Listeners en `EventServiceProvider`, todos usando `DestinatariosPorRolService`
  para evitar duplicados

**Checkpoint**: US1 + US2 funcionan de forma independiente

---

## Phase 5: User Story 3 - Notificación automática al cliente por correo (Priority: P2)

**Goal**: Correo automático al cliente en hitos de OT y Cotización, con reintento si falla

**Independent Test**: Completar entrega de una OT, verificar correo recibido; simular fallo SMTP y
verificar registro para reintento

### Tests for User Story 3

- [ ] T023 [P] [US3] Feature test `tests/Feature/Notificaciones/NotificacionClienteTest.php`: OT
  "Entregada" → correo enviado al cliente
- [ ] T024 [P] [US3] Feature test `tests/Feature/Notificaciones/ReintentoFallidoTest.php`: fallo de envío
  queda en `failed_jobs`, reintentable sin perder el evento origen (FR-007)

### Implementation for User Story 3

- [ ] T025 [US3] `app/Notifications/OtEntregadaClienteNotification.php` (canal `mail`)
- [ ] T026 [P] [US3] `app/Notifications/CotizacionEnviadaClienteNotification.php` (reutiliza el envío ya
  implementado en spec 006, no lo duplica)
- [ ] T027 [US3] Configurar `retry_after`/colas con reintento (`ShouldQueue` + `$tries`) en las
  notificaciones de canal `mail`

**Checkpoint**: Las 3 historias de usuario son funcionales de forma independiente

---

## Phase 6: Polish & Cross-Cutting Concerns

- [ ] T028 Documentar en cada spec de origen (002, 003, 005, 006) qué evento deben disparar exactamente
  (nombre de clase + payload) para que este módulo los consuma sin ambigüedad
- [ ] T029 Configurar Supervisor (o equivalente) para `php artisan queue:work` en producción (Hostinger) —
  tarea de despliegue, no de código
- [ ] T030 Ejecutar `php artisan queue:work` local + `php artisan test --filter=Notificaciones` en verde

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
