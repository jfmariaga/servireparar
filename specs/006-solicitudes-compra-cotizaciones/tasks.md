# Tasks: Solicitudes de Compra y Gestión de Cotizaciones

**Input**: Design documents from `specs/006-solicitudes-compra-cotizaciones/` (plan.md, spec.md)

**Prerequisites**: spec 000 (Cliente, Proveedor) y spec 003 (Inventario, maestra de insumos) implementados

**Tests**: Incluidos — el flujo de correo se testea con un fake de `ProveedorCorreoEntrante`, sin red real.

**Implementado 2026-09-26** (única spec del backlog que seguía en 0%). Suite completa: 363 tests en verde.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 `composer require webklex/php-imap` (`barryvdh/laravel-dompdf` ya estaba instalado, compartido
  con spec 007).
- [X] T002 Placeholders en `.env.example`/`config/services.php` (`services.correo_cotizaciones`) — sin
  cuenta de correo oficial real todavía; el sistema funciona íntegramente con el fake de
  `ProveedorCorreoEntrante` hasta que exista.

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Bloquea las 4 historias de usuario

- [X] T003 Migración `2026_09_26_100000_create_servicios_table.php`.
- [X] T004 [P] Migración `2026_09_26_100001_create_cotizaciones_table.php`.
- [X] T005 [P] Migración `2026_09_26_100002_create_detalle_cotizacion_table.php`.
- [X] T006 [P] Migración `2026_09_26_100003_create_mensajes_cotizacion_table.php` (`message_id_correo`
  indexado para el matcheo de hilo).
- [X] T007 [P] Migración `2026_09_26_100004_create_compras_table.php`.
- [X] T008 [P] Migración `2026_09_26_100005_create_detalle_compra_table.php`.
- [X] T009 `app/Contracts/ProveedorCorreoEntrante.php` / `ProveedorCorreoSaliente.php` + DTO
  `app/Contracts/MensajeCorreoEntrante.php` (correo ya parseado, agnóstico del protocolo).
- [X] T010 `app/Services/Correo/ImapPollingProveedorCorreo.php` (sobre `webklex/php-imap`) +
  `app/Services/Correo/MailProveedorCorreoSaliente.php` (Laravel Mail, fija el Message-ID explícitamente
  para poder guardarlo). Bindeados en `AppServiceProvider::register()`.
- [X] T011 Modelos Eloquent: `Cotizacion`, `DetalleCotizacion`, `MensajeCotizacion`, `Servicio`, `Compra`,
  `DetalleCompra` — con factories para tests.
- [X] T012 `app/Policies/CotizacionPolicy.php` y `CompraPolicy.php`, registradas en
  `AuthServiceProvider`; permisos `manage-cotizaciones`/`manage-compras` en `RolesSeeder` (solo
  Administrador).

**Checkpoint**: Interfaces de correo + modelo de datos listos ✅

---

## Phase 3: User Story 1 - Recepción y registro de solicitudes de cotización desde correo (Priority: P1) 🎯 MVP

**Goal**: Correo entrante → caso "En revisión"; correo no reconocible → notificación al Administrador

### Tests for User Story 1

- [X] T013 [P] [US1] `tests/Feature/Cotizaciones/RecepcionCorreoTest.php` — correo válido con/sin cliente
  identificado automáticamente, primer mensaje del hilo persistido.
- [X] T014 [P] [US1] `tests/Feature/Cotizaciones/CorreoFueraDeHiloTest.php` — correo sin plantilla
  reconocible y respuesta fuera de hilo, ambos notifican sin crear caso.

### Implementation for User Story 1

- [X] T015 [US1] `app/Services/Cotizaciones/ProcesarCorreoEntranteService.php`. **Decisión de diseño
  añadida (no estaba en el plan original)**: la "plantilla esperada" de FR-001 se resuelve con un
  disparador configurable en el asunto (`config('cotizaciones.asunto_disparador')`, default `"cotiz"`) —
  sin este match Y sin ser respuesta de un hilo conocido, se notifica en vez de crear caso. El match de
  cliente remitente es por `Cliente.correo` exacto; sin match, la Cotización queda sin cliente (se asigna
  manualmente desde `Gestionar`).
- [X] T016 [US1] `app/Console/Commands/ProcesarCorreoCotizaciones.php`, agendado en `routes/console.php`
  vía cron dinámico (`config('cotizaciones.polling_minutos')`, default 5).
- [X] T017 [US1] `resources/views/livewire/cotizaciones/tablero.blade.php` (bandeja con filtro por estado,
  aviso de cotizaciones sin cliente identificado).
- [X] T018 [US1] `resources/views/livewire/cotizaciones/gestionar.blade.php` — información general,
  asignación manual de cliente cuando no hay match automático.

**Checkpoint**: Recepción automática funcional — MVP del módulo ✅

---

## Phase 4: User Story 2 - Construir y enviar cotización usando la maestra de servicios/insumos (Priority: P1)

### Tests for User Story 2

- [X] T019 [P] [US2] `tests/Feature/Cotizaciones/ConstruirCotizacionTest.php`.
- [X] T020 [P] [US2] `tests/Feature/Cotizaciones/EnvioCotizacionTest.php` (incluye validaciones: sin
  cliente asignado o sin ítems, el envío falla explícitamente).
- [X] Cobertura adicional de UI: `tests/Feature/Cotizaciones/CotizacionesUiTest.php` (construir/enviar/
  asignar cliente desde el componente Livewire, no solo desde el Service).

### Implementation for User Story 2

- [X] T021 [US2] `app/Services/Cotizaciones/CotizacionService.php` (guardar ítems con reemplazo atómico,
  recalcular total, enviar, marcar entregada/facturada, asignar cliente — cada transición manual deja
  traza en el hilo, FR-007).
- [X] T022 [US2] `app/Services/Cotizaciones/GenerarPdfCotizacionService.php` (dompdf, mismo patrón que
  `RemisionEntregaController`/`RemisionEntregada`); PDF generado inline en `CotizacionEnviada::attachments()`
  (`Attachment::fromData`), no como archivo intermedio en disco.
- [X] T023 [US2] `resources/views/livewire/cotizaciones/servicios-maestra.blade.php` (CRUD, mismo patrón
  que `personal/especialidades`).
- [X] T024 [US2] UI de construcción de ítems (servicio/insumo, cantidad) + botón Guardar/Enviar integrada
  en `gestionar.blade.php`.

**Checkpoint**: US1 + US2 funcionan de forma independiente ✅

---

## Phase 5: User Story 3 - Seguimiento de respuesta del cliente y cierre del caso (Priority: P1)

### Tests for User Story 3

- [X] T025 [P] [US3] `tests/Feature/Cotizaciones/RespuestaClienteTest.php` — aceptación/rechazo detectado
  por palabras clave en el cuerpo del correo de respuesta (matcheado por `message_id_correo` en
  `References`, SC-003).
- [X] T026 [P] [US3] Avance manual Aceptada → Entregada → Facturada, con mensaje de sistema en el hilo por
  cada transición (SC-004), cubierto en el mismo test file.

### Implementation for User Story 3

- [X] T027 [US3] `ProcesarCorreoEntranteService::agregarAlHilo()`/`detectarRespuesta()` — matchea por
  `message_id_correo` contra las `referencias` (`In-Reply-To`/`References`) del correo entrante; detecta
  aceptación/rechazo por palabras clave simples (`acepto`, `rechazo`, etc. — heurística documentada en el
  código, ajustable) solo mientras el estado es `cotizada`.
- [X] T028 [US3] Acciones "Marcar entregada"/"Marcar facturada" en `gestionar.blade.php`.

**Checkpoint**: Ciclo comercial completo funcional ✅

---

## Phase 6: User Story 4 - Solicitudes de compra a proveedores (Priority: P2)

### Tests for User Story 4

- [X] T029 [P] [US4] `tests/Feature/Compras/FlujoCompraTest.php` — transición completa con fecha
  registrada en cada paso, no permite saltar estados.
- [X] Cobertura adicional de UI: `tests/Feature/Compras/ComprasUiTest.php`.

### Implementation for User Story 4

- [X] T030 [US4] `app/Services/Compras/CompraService.php` (crear con ítems, avanzar por los 4 estados con
  guarda de transición y timestamp).
- [X] T031 [US4] `resources/views/livewire/compras/tablero.blade.php`, `form.blade.php` (creación) y
  `gestionar.blade.php` (avance de estados) — tres componentes en vez de uno solo, siguiendo el mismo
  patrón de Cotizaciones (lista/crear/gestionar separados).
- [X] T032 [US4] Rutas `/compras`, `/compras/nueva`, `/compras/{compra}` en `routes/web.php`, bajo
  `middleware('permission:manage-compras')`.

**Checkpoint**: Las 4 historias de usuario son funcionales de forma independiente ✅

---

## Phase 7: Polish & Cross-Cutting Concerns

- [X] T033 Matcheo automático de cliente remitente: **match exacto por `Cliente.correo`** (decisión del
  usuario, 2026-09-26); sin match, la Cotización queda sin cliente y el Administrador lo asigna al abrir
  el caso desde `gestionar.blade.php` — el hilo de correo (`message_id_correo`/`correo_original_referencia`)
  sigue funcionando igual, con o sin cliente identificado.
- [X] T034 `php artisan test tests/Feature/Cotizaciones tests/Feature/Compras` → 23/23 en verde; suite
  completa del proyecto → **363/363**.
- Ítems de navegación agregados en `resources/views/partials/nav-items.blade.php` (Cotizaciones, Compras),
  visibles solo para quien tiene el permiso correspondiente.

---

## Dependencias reales fuera de este módulo (no bloquean, quedan pendientes)

- Credenciales IMAP/SMTP reales de la cuenta de correo oficial (`.env`) — el sistema funciona con el fake
  en tests hasta entonces; `ImapPollingProveedorCorreo` no se ha ejercitado contra un servidor real.
- `CotizacionEnviadaClienteNotification`/reutilización de este envío en spec 008 (T026 de ese spec) — ahora
  que 006 existe, ese punto de spec 008 ya no está bloqueado, queda como trabajo futuro opcional.

## Dependencies & Execution Order

- **Setup + Foundational** bloquean todo — las interfaces de correo son la base de US1/US3.
- **US1** es el MVP (sin recepción, no hay caso que gestionar).
- **US2** depende de US1 (necesita un caso en revisión para construir la cotización).
- **US3** depende de US2 (necesita una cotización enviada para poder responderla).
- **US4** (Compras a proveedores) es independiente de US1-US3 — reutiliza el patrón de estados pero no la
  infraestructura de correo entrante, se implementó en paralelo.

## Implementation Strategy

Implementado todo de una vez (decisión del usuario, 2026-09-26), en vez de MVP por fases: US1+US2+US3+US4
completas en el mismo corte.
