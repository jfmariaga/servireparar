# Implementation Plan: Gestión de Inventario (Bodega)

**Branch**: `003-inventario-bodega` | **Date**: 2026-08-24 · **Rev.**: 2026-09-01 (US6 venta sin OT) | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/003-inventario-bodega/spec.md`

> **Revisión 2026-09-01**: se añade el canal de **venta mostrador sin OT** (spec 003, US6; FR-019..FR-025)
> y el rol **Vendedor** (spec 001). El diseño incremental de esta revisión está en la sección
> [Revisión 2026-09-01 — Venta mostrador sin OT](#revisión-2026-09-01--venta-mostrador-sin-ot) al final de
> este documento; el cuerpo original se conserva sin cambios salvo la extensión del enum `origen` en
> `movimientos_inventario`.

## Summary

Módulo de bodega: catálogo de ítems con categorías reales (Llantas, EPP, Tuberías y Láminas, Insumos,
Pinturas, Herramientas, Repuestos), codificación por prefijo + ubicación física `Pasillo-Estante-Nivel`,
generación de código de barras Code128, movimientos de entrada/salida/devolución, solicitudes de insumo
(desde OT o manuales), y auditoría de conteo físico con aprobación administrativa de ajustes. Depende de
spec 000 (Proveedor) para entradas y se integra bidireccionalmente con spec 002 (OT).

## Technical Context

**Language/Version**: PHP 8.2, Laravel 11 (^11.31)

**Primary Dependencies**: Livewire 3.6.4 + Volt 1.7, spatie/laravel-permission 6.9 (Almacenista gestiona
movimientos, Administrador aprueba ajustes de auditoría), librería de generación de códigos de barra
Code128 (ej. `picqer/php-barcode-generator`, sin dependencias de sistema operativo — genera SVG/PNG en
PHP puro, compatible con Hostinger shared/VPS sin extensiones nativas adicionales)

**Storage**: MySQL/MariaDB — `inventario`, `categorias_inventario`, `movimientos_inventario`,
`auditorias_inventario`, `ajustes_auditoria`; imágenes de código de barra generadas on-demand (no
almacenadas, se renderizan al imprimir) o cacheadas en `storage/app/public/barcodes`

**Testing**: Pest/PHPUnit — feature tests de movimientos (entrada/salida/devolución), bloqueo de stock
insuficiente (FR-009), flujo de auditoría con aprobación (doble validación), generación de código interno
por prefijo y validación de formato de ubicación

**Target Platform**: Servidor Linux (Hostinger VPS), aplicación Laravel monolítica

**Project Type**: Web application — monolito Laravel + Livewire/Volt

**Performance Goals**: Descuento de stock debe ser atómico bajo concurrencia (edge case del spec) — usar
transacciones de base de datos con `lockForUpdate()` sobre la fila de `inventario` al descontar

**Constraints**: El lector de código de barras es USB HID (actúa como teclado) — no requiere JavaScript de
cámara ni permisos de hardware del navegador; el campo de búsqueda/escaneo es un `<input>` estándar que
captura el string + Enter que emite el lector

**Scale/Scope**: Cientos de ítems de inventario (ver Excel real: ~200+ filas por hoja en varias categorías),
movimientos diarios recurrentes — requiere índices en `codigo`, `categoria_id`, `ubicacion`

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- ✅ **I. Stack Tecnológico Fijo**: Livewire/Volt; la librería de códigos de barra es una adición menor y
  puntual (generación de imagen), no reemplaza ni compite con el stack base — se justifica como utilidad,
  no como framework alternativo.
- ✅ **II. Roles y Autorización**: Almacenista gestiona movimientos; solo Administrador aprueba ajustes de
  auditoría (`AuditoriaPolicy@approve`), vía spatie-permission.
- ✅ **III. Alcance Cerrado**: categorías, ubicación y código de barras están dentro del alcance ampliado
  documentado en la constitución v1.1.0 tras validación con el manual de almacén real.
- ✅ **IV. Trazabilidad**: todo movimiento queda en `movimientos_inventario` con usuario y fecha; ajustes de
  auditoría quedan en `ajustes_auditoria` con estado propuesto/aprobado/rechazado y quién decidió.
- ✅ **V. Entrega Modular por Fases**: demostrable de forma aislada (CRUD de inventario + movimientos
  manuales) sin requerir que OT (spec 002) ya esté completo, aunque se integran cuando ambos existen.

Sin violaciones. No se requiere Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/003-inventario-bodega/
├── plan.md              # This file
└── spec.md              # Feature specification (ya existente)
```

### Source Code (repository root)

```text
app/
├── Models/
│   ├── Inventario.php
│   ├── CategoriaInventario.php
│   ├── MovimientoInventario.php
│   ├── AuditoriaInventario.php
│   └── AjusteAuditoria.php
├── Services/
│   └── Inventario/
│       ├── CodigoInternoService.php      # asigna prefijo (REP-/HER-/CON-/ACC-) + consecutivo
│       ├── MovimientoService.php         # entradas/salidas/devoluciones con lock atómico de stock
│       ├── SolicitudInsumoService.php    # consumido también por spec 002
│       └── BarcodeService.php            # genera Code128 (wrapper sobre picqer/php-barcode-generator)
├── Livewire/
│   └── Inventario/
│       ├── Tablero.php                   # dashboard Almacenista (Ilustración 17)
│       ├── Catalogo.php                  # listado/CRUD de ítems, con categoría y ubicación
│       ├── SolicitudManual.php           # Ilustración 18
│       ├── Devolucion.php
│       └── Auditoria.php                 # iniciar auditoría, registrar conteo, ver ajustes pendientes
└── Policies/
    ├── InventarioPolicy.php
    └── AuditoriaPolicy.php               # approve() solo Administrador

database/
├── migrations/
│   ├── xxxx_create_categorias_inventario_table.php
│   ├── xxxx_create_inventario_table.php
│   ├── xxxx_create_movimientos_inventario_table.php
│   ├── xxxx_create_auditorias_inventario_table.php
│   └── xxxx_create_ajustes_auditoria_table.php
└── seeders/
    └── CategoriasInventarioSeeder.php    # siembra las 7 categorías reales

routes/
└── web.php               # /inventario, /inventario/solicitudes, /inventario/auditorias

tests/
└── Feature/
    └── Inventario/
        ├── MovimientoTest.php
        ├── SolicitudManualTest.php
        ├── DevolucionHerramientaTest.php
        ├── AuditoriaAjusteTest.php
        └── CodificacionUbicacionTest.php
```

**Structure Decision**: Monolito Laravel; `BarcodeService` se aísla para poder cambiar de librería sin
afectar el resto del módulo, y `MovimientoService` centraliza toda mutación de stock para garantizar
atomicidad (ningún Livewire component descuenta stock directamente).

## Data Model

### CategoriaInventario (`categorias_inventario`) — nueva

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nombre | string(100) | Semilla: Llantas, EPP, Tuberías y Láminas, Insumos, Pinturas, Herramientas, Repuestos |
| prefijo_codigo | string(4) | Mapeo a `REP-`/`HER-`/`CON-`/`ACC-` (ver Assumptions del spec) |

### Inventario (`inventario`)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| codigo | string(20) unique | Ej. `REP-001`, generado por `CodigoInternoService` |
| nombre | string(150) | |
| tipo | enum('herramienta','consumible') | Atributo independiente de la categoría |
| categoria_id | FK → `categorias_inventario` | |
| ubicacion | string(20) nullable | Formato `PASILLO-ESTANTE-NIVEL`, ej. `A-01-01` |
| codigo_barras | string(50) nullable | Valor codificado en el Code128 (normalmente = `codigo`) |
| unidad_medida | string(20) nullable | |
| stock_actual | decimal | |
| stock_minimo | decimal default 0 | |
| costo_unitario | decimal(12,2) nullable | Usado por spec 002 (costeo) y spec 006 |
| estado_herramienta | enum('disponible','en_uso','dañada','en_mantenimiento') nullable | Solo aplica si `tipo = herramienta` |
| activo | boolean default true | |
| timestamps | | |

### MovimientoInventario (`movimientos_inventario`)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| inventario_id | FK | |
| tipo_mov | enum('entrada','salida','devolucion') | |
| cantidad | decimal | |
| fecha | timestamp | |
| motivo | string nullable | |
| referencia | string nullable | Ej. `numero_ot` si viene de spec 002 |
| usuario_id | FK → `users` | |
| proveedor_id | FK → `proveedores` (spec 000) nullable | Solo en entradas |
| origen | enum('ot','manual','entrada_proveedor','devolucion','despacho') | Distingue solicitudes vía OT, manuales (US2) y del canal de venta sin OT (US6, `despacho` — ver revisión 2026-09-01) |
| cliente_id | FK → `clientes` (spec 000) nullable | Solo en salidas manuales a cliente externo |

### AuditoriaInventario / AjusteAuditoria — nuevas

| Campo (Auditoria) | Tipo |
|---|---|
| id | bigint PK |
| iniciada_por | FK → `users` |
| fecha_inicio | timestamp |
| fecha_cierre | timestamp nullable |

| Campo (AjusteAuditoria) | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| auditoria_id | FK | |
| inventario_id | FK | |
| stock_sistema | decimal | Snapshot al momento del conteo |
| stock_fisico | decimal | Reportado por el Almacenista |
| motivo | string | |
| estado | enum('pendiente','aprobado','rechazado') | |
| aprobado_por | FK → `users` nullable | |
| resuelto_en | timestamp nullable | |

## Verificación end-to-end

1. `php artisan db:seed --class=CategoriasInventarioSeeder` siembra las 7 categorías reales.
2. Registrar un ítem nuevo categoría "Repuestos" → verificar código `REP-00001` autogenerado y validación
   de formato de ubicación (`A-01-01`).
3. Generar e imprimir el código de barras del ítem (descarga PNG/SVG); simular escaneo pegando el valor en
   el campo de búsqueda de un formulario de movimiento → debe autocompletar el ítem.
4. Con una OT (spec 002) que solicita un insumo, aprobarla y entregarla desde `/inventario/solicitudes` →
   verificar descuento de stock y, si aplica, cambio de estado de herramienta a "En uso".
5. Reducir stock de un consumible bajo su `stock_minimo` → verificar disparo de alerta (integración con
   spec 008, aunque ese módulo aún no exista, el evento debe emitirse).
6. Iniciar una auditoría, registrar una diferencia, aprobarla como Administrador → verificar que el
   `stock_actual` solo cambia tras la aprobación, no al registrar el conteo.
7. `php artisan test --filter=Inventario` en verde.

---

## Revisión 2026-09-01 — Venta mostrador sin OT

### Alcance de la revisión

Canal comercial de salida de mercancía sin Orden de Trabajo (spec 003, User Story 6, FR-019..FR-025):
Vendedor recibe la solicitud del cliente → valida contra inventario → crea **Solicitud de Despacho** con
líneas → Almacenista la recibe, genera **Remisión de Entrega** con consecutivo → cliente **firma digital
en pantalla** → al confirmar la entrega se generan los movimientos de salida (`origen: despacho`). Las
líneas que el almacén no tiene se marcan como **compra externa**: quedan como registro trazable en la
línea (proveedor externo + costo + motivo fijo "No disponible en almacén"), sin ítem, sin lote y sin
movimiento de inventario.

**Dependencia con spec 001**: el rol **Vendedor** (5º rol) se agrega en el plan/tasks de spec 001
(`RolPrioridad` + `RolesSeeder` + redirección post-login + dashboard placeholder). Este plan asume que ese
rol y el permiso `manage-despachos` ya existen; las rutas del canal van detrás de
`middleware('role:Vendedor|Almacenista|Administrador')` + policies.

### Technical Context (incremental)

- **PDF de remisión**: `barryvdh/laravel-dompdf` (ya previsto en el plan transversal para exportaciones de
  spec 006/007) — render server-side de la remisión imprimible, con la firma embebida como `data:` URI.
- **Captura de firma**: lienzo HTML5 (`<canvas>` + JS mínimo inline, patrón HID-friendly como el del
  escáner: sin librería externa ni cámara). El trazo se serializa a PNG base64 en un `<input type="hidden">`
  que Livewire persiste. Sin dependencia backend nueva.
- **Consecutivos** `SD-#####` / `REM-#####`: servicio `ConsecutivoDespachoService` siguiendo el patrón de
  `CodigoInternoService` (prefijo + `str_pad` a 5, `max` con `lockForUpdate()` dentro de la transacción de
  creación para evitar colisiones bajo concurrencia). Numeración global corrida, no anual.
- **Momento del descuento de stock**: solo al confirmar la entrega firmada. Ninguna transición previa
  (`solicitada` → `recibida` → `remisionada`) toca `stock_actual`. La validación de disponibilidad se hace
  (a) informativa al crear la solicitud y (b) bloqueante al confirmar cada línea de inventario (FR-009,
  reusa `MovimientoService::salida()` que ya lanza `StockInsuficienteException`).

### Constitution Check (re-evaluación)

- ✅ **I. Stack fijo**: dompdf ya contemplado; la captura de firma es JS inline, no un framework.
- ✅ **II. Roles**: Vendedor crea/anula solicitudes (no descuenta stock); Almacenista recibe/remisiona/
  entrega; Administrador todo. `SolicitudDespachoPolicy` + `role:` middleware.
- ✅ **III. Alcance cerrado**: US6 formaliza la "funcionalidad diferencial" ya nombrada en la cotización
  (consumo de repuestos para externos sin OT); la compra externa se registra ligera, sin abrir el módulo
  de Compras (spec 006).
- ✅ **IV. Trazabilidad**: `solicitudes_despacho` + `detalle_solicitud_despacho` + `remisiones_entrega`
  guardan responsable y fecha en cada transición; cada línea de inventario genera su `movimientos_inventario`
  (`origen: despacho`, `referencia` = número de remisión); las líneas de compra externa quedan con
  proveedor/costo/motivo.
- ✅ **V. Entrega modular**: demostrable de forma aislada (Vendedor crea → Almacén entrega → remisión PDF)
  sin depender de spec 002; reutiliza `MovimientoService` existente.

Sin violaciones.

### Project Structure (incremental)

```text
app/
├── Models/
│   ├── SolicitudDespacho.php            # numero, cliente_id, vendedor_id, estado, timestamps de transición
│   ├── DetalleSolicitudDespacho.php     # origen inventario|compra_externa; FK inventario_id nullable
│   └── RemisionEntrega.php              # numero, solicitud_id (1:1), firma, recibido_por_*
├── Services/
│   └── Inventario/
│       ├── ConsecutivoDespachoService.php   # SD-##### / REM-##### con lock
│       └── DespachoService.php              # transiciones de estado + generación de remisión + entrega
│                                            #   (llama a MovimientoService::salida por cada línea de inventario)
├── Livewire/
│   └── Despacho/
│       ├── Index.php                    # bandeja: Vendedor ve las suyas; Almacenista ve todas por estado
│       ├── Form.php                     # alta/edición de solicitud + líneas (Vendedor)
│       └── Entrega.php                  # Almacenista: recibir → remisionar → capturar firma → entregar
├── Http/Controllers/
│   └── RemisionEntregaController.php    # GET /despachos/{solicitud}/remision → PDF dompdf
└── Policies/
    └── SolicitudDespachoPolicy.php      # create/update/anular: Vendedor+Admin; recibir/remisionar/entregar: Almacenista+Admin

database/
├── migrations/
│   ├── xxxx_create_solicitudes_despacho_table.php
│   ├── xxxx_create_detalle_solicitud_despacho_table.php
│   ├── xxxx_create_remisiones_entrega_table.php
│   └── xxxx_add_despacho_to_movimientos_inventario_origen.php   # extiende enum origen
└── (sin seeders nuevos)

resources/views/
├── livewire/despacho/{index,form,entrega}.blade.php
└── pdf/remision-entrega.blade.php       # plantilla dompdf

routes/
└── web.php   # /despachos, /despachos/nueva, /despachos/{s}, /despachos/{s}/remision

tests/
└── Feature/Despacho/
    ├── CrearSolicitudDespachoTest.php       # Vendedor; clasificación inventario vs compra_externa (FR-019, FR-020)
    ├── CompraExternaTrazaTest.php           # no crea ítem/lote/movimiento (FR-021)
    ├── FlujoEntregaFirmadaTest.php          # recibir→remisionar→firmar→entregar; salida origen:despacho + FIFO (FR-022..FR-024)
    ├── StockInsuficienteEnEntregaTest.php   # bloqueo al confirmar (FR-009 / escenario 7)
    ├── AnularSolicitudTest.php              # anular no toca stock (escenario 6)
    └── RemisionPdfTest.php                  # consecutivo REM-##### único + firma embebida (SC-007)
```

### Data Model (incremental)

#### SolicitudDespacho (`solicitudes_despacho`) — nueva

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| numero | string(12) unique | `SD-00001`, `ConsecutivoDespachoService` |
| cliente_id | FK → `clientes` (spec 000) | |
| vendedor_id | FK → `users` | Usuario con rol Vendedor que la crea |
| estado | enum('borrador','solicitada','recibida','remisionada','entregada','anulada') | default `solicitada` al enviar; `borrador` opcional |
| observaciones | text nullable | |
| fecha_solicitud | timestamp | |
| recibida_por | FK → `users` nullable | Almacenista |
| recibida_en | timestamp nullable | |
| remisionada_por | FK → `users` nullable | |
| remisionada_en | timestamp nullable | |
| entregada_en | timestamp nullable | |
| anulada_por | FK → `users` nullable | |
| motivo_anulacion | string nullable | |
| timestamps | | |

Índices: `estado`, `vendedor_id`, `cliente_id`.

#### DetalleSolicitudDespacho (`detalle_solicitud_despacho`) — nueva

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| solicitud_id | FK → `solicitudes_despacho` cascade | |
| origen | enum('inventario','compra_externa') | |
| inventario_id | FK → `inventario` nullable | Obligatorio si `origen = inventario`, null si compra externa |
| descripcion | string(200) nullable | Texto libre; obligatorio si `origen = compra_externa` |
| cantidad | decimal(12,2) | |
| costo_unitario | decimal(12,2) nullable | Referencia; en `inventario` lo fija el costeo FIFO al entregar |
| proveedor_externo | string(150) nullable | Solo `compra_externa` |
| costo_compra_externa | decimal(12,2) nullable | Solo `compra_externa` |
| motivo | string nullable | Fijo "No disponible en almacén" en `compra_externa` |
| movimiento_id | FK → `movimientos_inventario` nullable | Se rellena al entregar (solo líneas de inventario) |
| timestamps | | |

Regla de integridad (validación de aplicación, no CHECK): `origen = inventario` ⇒ `inventario_id` no nulo y
campos de compra externa nulos; `origen = compra_externa` ⇒ `descripcion` + `proveedor_externo` +
`costo_compra_externa` y `inventario_id` nulo.

#### RemisionEntrega (`remisiones_entrega`) — nueva

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| numero | string(12) unique | `REM-00001` |
| solicitud_id | FK → `solicitudes_despacho` unique | 1:1 |
| generada_por | FK → `users` | Quien la generó en el sistema |
| entregado_por_nombre | string(150) nullable | Quien entrega físicamente (FR-026; distinto de `generada_por`) |
| fecha | timestamp | |
| recibido_por_nombre | string(150) nullable | Quien recibe físicamente (se fija al confirmar) |
| recibido_por_documento | string(40) nullable | |
| firma | longText nullable | PNG base64 del trazo capturado en pantalla (FR-024) |
| nota_entrega | text nullable | Narración libre de la entrega (FR-026) |
| entregada_en | timestamp nullable | Se fija al confirmar la entrega |
| enviada_al_cliente_en | timestamp nullable | Marca de envío de la copia PDF al correo del cliente (FR-027) |

**PDF (FR-026)**: `pdf/remision-entrega.blade.php` replica el formato físico "REMISIÓN {sede} Nº ####"
(`config/despachos.php` → `DESPACHO_SEDE`); una sola tabla de artículos (Cant./Referencia/Descripción, sin
costos), datos del cliente de spec 000, bloques Entrega/Recibe + firma. No expone la clasificación interna
inventario/compra_externa.

**Correo (FR-027)**: `App\Mail\RemisionEntregada` (markdown + PDF adjunto), enviado por
`DespachoService::confirmarEntrega()` **después** de la transacción a `Cliente.correo`; un fallo de correo
no revierte la entrega.
| timestamps | | |

#### movimientos_inventario — cambio

`origen` pasa a `enum('ot','manual','entrada_proveedor','devolucion','despacho')`. Al entregar una línea de
inventario: `MovimientoService::salida($item, $cantidad, $vendedorOAlmacenista, origen: 'despacho', cliente: $solicitud->cliente, referencia: $remision->numero)` — reusa el bloqueo atómico, el costeo FIFO
(FR-016) y el evento `StockBajo` ya existentes.

### Máquina de estados (SolicitudDespacho)

```text
(borrador) ──enviar──▶ solicitada ──recibir──▶ recibida ──generarRemision──▶ remisionada ──confirmarEntrega──▶ entregada
     │                     │                      │                              │
     └───────── anular ────┴──────── anular ──────┴─────────── anular ───────────┘        (entregada = terminal)
```

- Transiciones y su actor autorizado (policy): `enviar` Vendedor/Admin · `recibir`/`generarRemision`/
  `confirmarEntrega` Almacenista/Admin · `anular` Vendedor o Almacenista/Admin, solo si estado ≠
  `entregada`/`anulada`.
- `confirmarEntrega` es transaccional: valida stock de cada línea de inventario (bloquea si falta, FR-009 /
  escenario 7), crea un `movimientos_inventario` por línea de inventario, fija `detalle.movimiento_id`,
  `remision.entregada_en` y `solicitud.entregada_en`. Las líneas de compra externa no producen efecto en
  inventario.

### Verificación end-to-end (US6)

1. Como Vendedor: crear solicitud para un cliente con 2 líneas — una de un consumible con stock, otra
   descrita a mano marcada `compra_externa` (proveedor "Ferretería X", costo). Verificar `SD-00001`,
   estado `solicitada`, clasificación correcta de líneas.
2. Como Almacenista: `recibir` → estado `recibida`; `generarRemision` → `REM-00001`, estado `remisionada`.
3. Firmar en pantalla (nombre + documento del receptor) y `confirmarEntrega`:
   - la línea de inventario genera `movimientos_inventario.origen='despacho'`, descuenta `stock_actual` y
     consume lotes FIFO (costo real en el movimiento);
   - la línea de compra externa NO genera movimiento ni lote; queda con proveedor/costo/motivo;
   - `solicitud.estado = entregada` (terminal).
4. `GET /despachos/1/remision` devuelve el PDF con las 2 líneas y la firma embebida.
5. Intentar `confirmarEntrega` de una solicitud cuya línea de inventario excede el stock → bloqueado con
   mensaje de insuficiencia; el resto no se descuenta.
6. `anular` una solicitud en `remisionada` → estado `anulada`, `stock_actual` intacto.
7. `php artisan test --filter=Despacho` en verde; `php artisan test --filter=Inventario` sigue en verde.
