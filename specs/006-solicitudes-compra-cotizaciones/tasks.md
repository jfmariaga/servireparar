# Tasks: Solicitudes de Compra y Gestión de Cotizaciones

**Input**: Design documents from `specs/006-solicitudes-compra-cotizaciones/` (plan.md, spec.md)

**Prerequisites**: spec 000 (Cliente, Proveedor) y spec 003 (Inventario, maestra de insumos) implementados

**Tests**: Incluidos — el flujo de correo se testea con un fake de `ProveedorCorreoEntrante`, sin red real.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup (Shared Infrastructure)

- [ ] T001 `composer require webklex/php-imap barryvdh/laravel-dompdf`
- [ ] T002 Configurar credenciales IMAP/SMTP de la cuenta de correo oficial en `.env` (placeholder en
  desarrollo — usar `log` driver o buzón de pruebas)

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Bloquea las 4 historias de usuario

- [ ] T003 Migración `xxxx_create_servicios_table.php` (maestra nueva, complementa `inventario`)
- [ ] T004 [P] Migración `xxxx_create_cotizaciones_table.php`
- [ ] T005 [P] Migración `xxxx_create_detalle_cotizacion_table.php`
- [ ] T006 [P] Migración `xxxx_create_mensajes_cotizacion_table.php` (incluye `message_id_correo` para
  matcheo de hilo)
- [ ] T007 [P] Migración `xxxx_create_compras_table.php`
- [ ] T008 [P] Migración `xxxx_create_detalle_compra_table.php`
- [ ] T009 `app/Contracts/ProveedorCorreoEntrante.php` y `app/Contracts/ProveedorCorreoSaliente.php`
  (interfaces — decisión de diseño central del módulo)
- [ ] T010 `app/Services/Correo/ImapPollingProveedorCorreo.php` (implementación sobre webklex/php-imap)
- [ ] T011 Modelos Eloquent: `Cotizacion`, `DetalleCotizacion`, `MensajeCotizacion`, `Servicio`, `Compra`,
  `DetalleCompra`
- [ ] T012 `app/Policies/CotizacionPolicy.php` y `app/Policies/CompraPolicy.php` (gestión restringida a
  Administrador)

**Checkpoint**: Interfaces de correo + modelo de datos listos — las historias de usuario pueden
implementarse

---

## Phase 3: User Story 1 - Recepción y registro de solicitudes de cotización desde correo (Priority: P1) 🎯 MVP

**Goal**: Correo entrante → caso "En revisión"; correo no reconocible → notificación al Administrador

**Independent Test**: Con un fake de `ProveedorCorreoEntrante` que retorna un mensaje de prueba, verificar
creación del caso; con un mensaje fuera de hilo, verificar notificación en vez de descarte

### Tests for User Story 1

- [ ] T013 [P] [US1] Feature test `tests/Feature/Cotizaciones/RecepcionCorreoTest.php`: correo válido →
  Cotizacion en "en_revision"
- [ ] T014 [P] [US1] Feature test `tests/Feature/Cotizaciones/CorreoFueraDeHiloTest.php`: correo no
  vinculable → notificación interna (evento, sin persistir cotización nueva)

### Implementation for User Story 1

- [ ] T015 [US1] `app/Services/Cotizaciones/ProcesarCorreoEntranteService.php` (matchea hilo existente por
  `message_id_correo`/`References`, o crea caso nuevo)
- [ ] T016 [US1] `app/Console/Commands/ProcesarCorreoCotizaciones.php`, registrado en el Scheduler (cada
  5 min, configurable)
- [ ] T017 [US1] Componente Volt `app/Livewire/Cotizaciones/Tablero.php` (Ilustración 5)
- [ ] T018 [US1] Componente Volt `app/Livewire/Cotizaciones/Gestionar.php` — vista de información general
  (Ilustración 6)

**Checkpoint**: Recepción automática funcional — MVP del módulo

---

## Phase 4: User Story 2 - Construir y enviar cotización usando la maestra de servicios/insumos (Priority: P1)

**Goal**: Construcción con ítems de `servicios`/`inventario`, guardar borrador, enviar con PDF adjunto

**Independent Test**: Construir cotización, guardar sin enviar, reabrir, enviar; verificar PDF generado y
cambio de estado a "Cotizada"

### Tests for User Story 2

- [ ] T019 [P] [US2] Feature test `tests/Feature/Cotizaciones/ConstruirCotizacionTest.php`: cálculo
  automático de total con ítems de servicios e insumos
- [ ] T020 [P] [US2] Feature test `tests/Feature/Cotizaciones/EnvioCotizacionTest.php`: guardar como
  borrador no notifica; enviar genera PDF y cambia estado

### Implementation for User Story 2

- [ ] T021 [US2] `app/Services/Cotizaciones/CotizacionService.php` (construir, guardar, calcular total)
- [ ] T022 [US2] `app/Services/Cotizaciones/GenerarPdfCotizacionService.php` (dompdf)
- [ ] T023 [US2] Componente Volt `app/Livewire/Cotizaciones/ServiciosMaestra.php` (CRUD de servicios)
- [ ] T024 [US2] UI de construcción de cotización en `Gestionar.php` (Ilustraciones 7-8) + botón
  Guardar/Enviar/Cancelar

**Checkpoint**: US1 + US2 funcionan de forma independiente

---

## Phase 5: User Story 3 - Seguimiento de respuesta del cliente y cierre del caso (Priority: P1)

**Goal**: Captura automática de aceptación/rechazo en el mismo hilo; avance manual a entrega/facturación

**Independent Test**: Simular respuesta del cliente en el mismo hilo, verificar transición automática;
avanzar manualmente a Entregada y Facturada

### Tests for User Story 3

- [ ] T025 [P] [US3] Feature test `tests/Feature/Cotizaciones/RespuestaClienteTest.php`: aceptación/
  rechazo automático vía `message_id_correo` coincidente (SC-003)
- [ ] T026 [P] [US3] Feature test: avance manual Aceptada → Entregada → Facturada con historial completo
  (SC-004)

### Implementation for User Story 3

- [ ] T027 [US3] Extender `ProcesarCorreoEntranteService` para detectar aceptación/rechazo en respuestas
  del mismo hilo
- [ ] T028 [US3] Acciones "Marcar entrega" / "Marcar facturación" en `Gestionar.php` (Ilustraciones 10-11)

**Checkpoint**: Ciclo comercial completo funcional

---

## Phase 6: User Story 4 - Solicitudes de compra a proveedores (Priority: P2)

**Goal**: Flujo recepción → cotización → aprobación → facturación para compras a Proveedor (spec 000)

**Independent Test**: Registrar solicitud de compra, avanzar por sus 4 estados, verificar historial de
costos en `DetalleCompra`

### Tests for User Story 4

- [ ] T029 [P] [US4] Feature test `tests/Feature/Compras/FlujoCompraTest.php`: transición completa con
  fecha y responsable en cada paso

### Implementation for User Story 4

- [ ] T030 [US4] `app/Services/Compras/CompraService.php`
- [ ] T031 [US4] Componente Volt `app/Livewire/Compras/Gestionar.php`
- [ ] T032 [US4] Ruta `/compras`, `/compras/{compra}`

**Checkpoint**: Las 4 historias de usuario son funcionales de forma independiente

---

## Phase 7: Polish & Cross-Cutting Concerns

- [ ] T033 Matcheo automático de cliente remitente por correo contra `Cliente.correo` (spec 000) — punto
  no bloqueante dejado abierto en `/speckit-clarify`, resolver aquí con fallback a selección manual
- [ ] T034 Ejecutar `php artisan test --filter=Cotizaciones` y `--filter=Compras` en verde

---

## Dependencies & Execution Order

- **Setup + Foundational** bloquean todo — las interfaces de correo son la base de US1/US3.
- **US1** es el MVP (sin recepción, no hay caso que gestionar).
- **US2** depende de US1 (necesita un caso en revisión para construir la cotización).
- **US3** depende de US2 (necesita una cotización enviada para poder responderla).
- **US4** (Compras a proveedores) es independiente de US1-US3 — reutiliza el patrón de estados pero no la
  infraestructura de correo entrante, puede desarrollarse en paralelo.

## Implementation Strategy

MVP = US1 + US2 (recepción + construcción/envío de cotización). US3 (seguimiento automático) es el segundo
incremento crítico. US4 (Compras) puede entregarse en paralelo por cualquier desarrollador sin bloquear el
resto.
