# Implementation Plan: Solicitudes de Compra y Gestión de Cotizaciones

**Branch**: `006-solicitudes-compra-cotizaciones` | **Date**: 2026-08-24 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/006-solicitudes-compra-cotizaciones/spec.md`

## Summary

Dos flujos de negocio paralelos sobre la misma infraestructura de correo: (1) Cotizaciones a clientes,
recibidas por IMAP polling, construidas desde una maestra de servicios/insumos y con seguimiento
automático de respuesta en el mismo hilo; (2) Compras a proveedores, con flujo manual de recepción →
cotización → aprobación → facturación. Depende de spec 000 (Cliente, Proveedor) y reutiliza `INVENTARIO`
(spec 003) como maestra de insumos.

## Technical Context

**Language/Version**: PHP 8.2, Laravel 11 (^11.31)

**Primary Dependencies**: Livewire 3.6.4 + Volt 1.7, Laravel Mail (SMTP saliente), un cliente IMAP en PHP
puro para el polling entrante (ej. `webklex/php-imap`, sin dependencia de la extensión nativa `ext-imap`
de PHP — importante porque Hostinger shared/VPS no siempre la tiene habilitada), Laravel Queue + Scheduler
(job programado de polling cada N minutos), `barryvdh/laravel-dompdf` o similar para generar el PDF de
cotización adjunto

**Storage**: MySQL/MariaDB — `cotizaciones`, `detalle_cotizacion`, `mensajes_cotizacion`, `compras`,
`detalle_compra`, `servicios` (maestra nueva, complementa `inventario` de spec 003)

**Testing**: Pest/PHPUnit — feature tests de construcción/envío de cotización, captura de respuesta
simulando un correo entrante parseado, flujo de compras a proveedor, y un test de contrato sobre la interfaz
`ProveedorCorreoEntrante` (mockeable, sin depender de un servidor IMAP real en CI)

**Target Platform**: Servidor Linux (Hostinger VPS), aplicación Laravel monolítica; requiere credenciales
IMAP/SMTP de una cuenta de correo oficial configuradas por `.env`

**Project Type**: Web application — monolito Laravel + Livewire/Volt + 1 job en background (polling IMAP)

**Performance Goals**: Polling IMAP cada 5 minutos (configurable) es suficiente — no es un canal de tiempo
real; SC-002 exige que construir/enviar una cotización tome < 5 minutos de interacción humana, no de
sistema

**Constraints**: El polling IMAP y el envío SMTP se implementan detrás de una interfaz
`ProveedorCorreoEntrante`/`ProveedorCorreoSaliente` (FR-010), de forma que un cambio futuro de proveedor de
correo no requiera tocar la lógica de negocio de Cotizaciones

**Scale/Scope**: Volumen de correo bajo (decenas de solicitudes/mes esperadas para un taller mediano); el
diseño no necesita optimizarse para alto throughput de correo

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- ✅ **I. Stack Tecnológico Fijo**: Livewire/Volt; las librerías de IMAP/PDF son utilidades puntuales
  detrás de interfaces propias, no reemplazan el stack base.
- ✅ **II. Roles y Autorización**: gestión de Cotizaciones y Compras restringida a Administrador vía
  `CotizacionPolicy`/`CompraPolicy`.
- ✅ **III. Alcance Cerrado**: mecanismo de correo (IMAP polling) ya decidido en `/speckit-clarify`, dentro
  del alcance aprobado.
- ✅ **IV. Trazabilidad**: cada Cotización mantiene su hilo completo de mensajes (`mensajes_cotizacion`), y
  cada Compra su historial de transición de estado con fecha y responsable.
- ✅ **V. Entrega Modular por Fases**: demostrable de forma aislada en Fase 3, aunque requiere spec 000
  (Cliente/Proveedor) y spec 003 (maestra de insumos) ya operativos.

Sin violaciones. No se requiere Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/006-solicitudes-compra-cotizaciones/
├── plan.md              # This file
└── spec.md              # Feature specification (ya existente)
```

### Source Code (repository root)

```text
app/
├── Models/
│   ├── Cotizacion.php
│   ├── DetalleCotizacion.php
│   ├── MensajeCotizacion.php
│   ├── Servicio.php                      # maestra nueva de servicios (complementa Inventario/insumos)
│   ├── Compra.php
│   └── DetalleCompra.php
├── Contracts/
│   ├── ProveedorCorreoEntrante.php       # interfaz: fetchNuevosMensajes(), marcarProcesado()
│   └── ProveedorCorreoSaliente.php       # interfaz: enviar($cotizacion, $adjuntoPdf)
├── Services/
│   └── Correo/
│       └── ImapPollingProveedorCorreo.php  # implementación concreta sobre webklex/php-imap
├── Services/
│   └── Cotizaciones/
│       ├── ProcesarCorreoEntranteService.php  # matchea hilo existente o crea caso nuevo / notifica al Administrador
│       ├── CotizacionService.php          # construir, guardar borrador, enviar, calcular total
│       └── GenerarPdfCotizacionService.php
├── Services/
│   └── Compras/
│       └── CompraService.php              # flujo recepción → cotización → aprobación → facturación
├── Livewire/
│   ├── Cotizaciones/
│   │   ├── Tablero.php                   # Ilustración 5
│   │   ├── Gestionar.php                 # Ilustraciones 6-11 (hilo + construcción + envío)
│   │   └── ServiciosMaestra.php          # CRUD del catálogo de servicios
│   └── Compras/
│       └── Gestionar.php
├── Console/
│   └── Commands/
│       └── ProcesarCorreoCotizaciones.php  # programado vía Scheduler, invoca ProcesarCorreoEntranteService
└── Policies/
    ├── CotizacionPolicy.php
    └── CompraPolicy.php

database/
└── migrations/
    ├── xxxx_create_servicios_table.php
    ├── xxxx_create_cotizaciones_table.php
    ├── xxxx_create_detalle_cotizacion_table.php
    ├── xxxx_create_mensajes_cotizacion_table.php
    ├── xxxx_create_compras_table.php
    └── xxxx_create_detalle_compra_table.php

routes/
└── web.php               # /cotizaciones, /cotizaciones/{cotizacion}, /compras, /compras/{compra}

tests/
└── Feature/
    ├── Cotizaciones/
    │   ├── RecepcionCorreoTest.php        # usa un fake de ProveedorCorreoEntrante
    │   ├── ConstruirCotizacionTest.php
    │   ├── EnvioCotizacionTest.php
    │   ├── RespuestaClienteTest.php
    │   └── CorreoFueraDeHiloTest.php
    └── Compras/
        └── FlujoCompraTest.php
```

**Structure Decision**: Monolito Laravel; el aislamiento detrás de `ProveedorCorreoEntrante`/
`ProveedorCorreoSaliente` (interfaces en `app/Contracts/`) es la decisión de diseño central de este módulo
— permite testear toda la lógica de negocio con un fake en memoria, sin depender de un servidor IMAP real
ni en tests ni en CI.

## Data Model

### Servicio (`servicios`) — maestra nueva

| Campo | Tipo |
|---|---|
| id | bigint PK |
| nombre | string(150) |
| costo_unitario | decimal(12,2) |
| unidad_medida | string(20) nullable |
| activo | boolean default true |

### Cotizacion (`cotizaciones`)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| numero | string(20) unique | Ej. `COT-1025`, formato visto en el mockup |
| cliente_id | FK → `clientes` (spec 000) nullable | Null si el remitente no fue identificado automáticamente |
| equipo_id | FK → `equipos` (spec 005) nullable | |
| estado | enum('en_revision','cotizada','aceptada','rechazada','entregada','facturada') | |
| correo_original_referencia | string nullable | Message-ID del correo entrante que originó el caso |
| total | decimal(12,2) | Calculado de `detalle_cotizacion` |
| timestamps | | |

### DetalleCotizacion (`detalle_cotizacion`)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| cotizacion_id | FK | |
| tipo_item | enum('servicio','insumo') | |
| servicio_id / inventario_id | FK nullable (uno u otro) | |
| cantidad | decimal | |
| costo_unitario | decimal(12,2) | |
| valor_total | decimal(12,2) | |

### MensajeCotizacion (`mensajes_cotizacion`)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| cotizacion_id | FK | |
| autor_tipo | enum('administrador','cliente','sistema') | |
| autor_id | FK → `users` nullable | Null si autor_tipo = cliente/sistema |
| contenido | text | |
| adjunto_url | string nullable | |
| message_id_correo | string nullable | Para matchear respuestas del mismo hilo (`In-Reply-To`/`References`) |
| created_at | timestamp | |

### Compra (`compras`) / DetalleCompra (`detalle_compra`) — ya definidos en spec 000/006 original, sin cambios

## Verificación end-to-end

1. Con `webklex/php-imap` configurado contra una cuenta de prueba (o un fake en tests), simular la
   recepción de un correo de solicitud de cotización → verificar que crea una `Cotizacion` en
   `en_revision`.
2. Simular un correo que no matchea ningún hilo → verificar que genera notificación interna al
   Administrador (integración con spec 008) en vez de descartarse silenciosamente.
3. Construir una cotización con ítems de `servicios` e `inventario`, guardar como borrador, reabrir,
   enviar → verificar generación de PDF y cambio de estado a "Cotizada".
4. Simular respuesta del cliente en el mismo hilo (mismo `Message-ID` en `References`) → verificar
   transición automática a "Aceptada"/"Rechazada" sin intervención manual.
5. Registrar una solicitud de compra a un proveedor (spec 000) y avanzarla por sus 4 estados → verificar
   trazabilidad completa en `DetalleCompra`.
6. `php artisan test --filter=Cotizaciones` y `--filter=Compras` en verde, usando el fake de
   `ProveedorCorreoEntrante` (sin red real).
