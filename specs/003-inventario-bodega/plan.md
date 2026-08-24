# Implementation Plan: Gestión de Inventario (Bodega)

**Branch**: `003-inventario-bodega` | **Date**: 2026-08-24 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/003-inventario-bodega/spec.md`

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
| origen | enum('ot','manual','entrada_proveedor','devolucion') | Distingue solicitudes vía OT vs. manuales (User Story 2) |
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
