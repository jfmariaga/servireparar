# Implementation Plan: Catálogos Maestros (Clientes, Proveedores, Contratistas)

**Branch**: `000-catalogos-maestros` | **Date**: 2026-08-24 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/000-catalogos-maestros/spec.md`

## Summary

CRUD de tres catálogos maestros independientes (Cliente, Proveedor, Contratista) sin flujo de estados
propio, implementado como recursos Livewire/Volt estándar con activo/inactivo (soft-state, no soft-delete).
Es el primer módulo a construir: los specs 002 (OT), 003 (Inventario) y 006 (Compras/Cotizaciones) dependen
de estos tres catálogos para poder registrar operaciones reales.

## Technical Context

**Language/Version**: PHP 8.2, Laravel 11 (^11.31)

**Primary Dependencies**: Livewire 3.6.4 + Volt 1.7 (UI reactiva sobre Blade), Laravel Sanctum 4.0 (sesión
autenticada), spatie/laravel-permission 6.9 (autorización de quién gestiona estos catálogos)

**Storage**: MySQL/MariaDB (motor por defecto en Hostinger), vía Eloquent ORM y migraciones de Laravel

**Testing**: Pest o PHPUnit (`phpunit/phpunit` ya en `composer.json`) — feature tests por catálogo
(creación, edición, activo/inactivo, validación de duplicados) + policy tests de autorización

**Target Platform**: Servidor Linux (Hostinger VPS), desplegado como aplicación Laravel monolítica

**Project Type**: Web application — monolito Laravel + Livewire/Volt (no separación frontend/backend)

**Performance Goals**: Listados con paginación estándar de Laravel (no requiere optimización especial dado
el volumen esperado — decenas/cientos de clientes/proveedores/contratistas, no miles)

**Constraints**: Debe estar completamente operativo antes de iniciar el desarrollo funcional de specs 002,
003 y 006 (ver FR-006 del spec)

**Scale/Scope**: 3 entidades CRUD simples, sin flujo de aprobación; alcance intencionalmente pequeño

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- ✅ **I. Stack Tecnológico Fijo**: usa exclusivamente Livewire/Volt + Eloquent, sin paquetes adicionales.
- ✅ **II. Roles y Autorización**: gestión restringida a Administrador y Jefe de Taller vía policies de
  spatie-permission (`manage-clientes`, `manage-proveedores`, `manage-contratistas`).
- ✅ **III. Alcance Cerrado**: catálogos explícitamente dentro del alcance ampliado documentado en la
  constitución (v1.1.0).
- ✅ **IV. Trazabilidad**: Laravel `created_at`/`updated_at` + campo `estado` cubren la trazabilidad básica
  requerida (no hay flujo de aprobación que auditar en este módulo).
- ✅ **V. Entrega Modular por Fases**: módulo demostrable de forma aislada (crear/editar/activar un
  registro) sin depender de otros módulos.

Sin violaciones. No se requiere sección de Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/000-catalogos-maestros/
├── plan.md              # This file
└── spec.md              # Feature specification (ya existente)
```

Se omiten `research.md`/`quickstart.md`/`contracts/` separados por ser un módulo CRUD simple sin
incertidumbre técnica — el Data Model se documenta inline abajo.

### Source Code (repository root)

Estructura de aplicación Laravel monolítica (Opción 1 adaptada a Laravel), compartida por todos los
módulos del proyecto:

```text
app/
├── Models/
│   ├── Cliente.php
│   ├── Proveedor.php
│   └── Contratista.php
├── Livewire/
│   └── Catalogos/
│       ├── Clientes/ (Index.php, Form.php — Volt single-file components)
│       ├── Proveedores/
│       └── Contratistas/
└── Policies/
    ├── ClientePolicy.php
    ├── ProveedorPolicy.php
    └── ContratistaPolicy.php

database/
├── migrations/
│   ├── xxxx_create_clientes_table.php
│   ├── xxxx_create_proveedores_table.php
│   └── xxxx_create_contratistas_table.php
└── seeders/
    └── CatalogosMaestrosSeeder.php   # datos de ejemplo para desarrollo/QA

routes/
└── web.php               # rutas /clientes, /proveedores, /contratistas (Volt::route)

tests/
└── Feature/
    └── Catalogos/
        ├── ClienteTest.php
        ├── ProveedorTest.php
        └── ContratistaTest.php
```

**Structure Decision**: Monolito Laravel estándar (`app/`, `database/`, `routes/`, `tests/`), sin capa de
API REST separada — Livewire/Volt renderiza directamente sobre las rutas web, consistente con el principio
I de la constitución (Livewire/Volt full-stack, sin SPA desacoplada).

## Data Model

### Cliente

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nombre | string(150) | Nombre o razón social |
| nit | string(20) nullable | Único cuando presente; se advierte duplicado, no se bloquea |
| telefono | string(20) nullable | |
| correo | string(150) nullable | |
| direccion | string(255) nullable | |
| estado | enum('activo','inactivo') default 'activo' | |
| timestamps | | |

### Proveedor

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nombre | string(150) | |
| nit | string(20) nullable | |
| correo | string(150) nullable | |
| direccion | string(255) nullable | |
| estado | enum('activo','inactivo') default 'activo' | |
| timestamps | | |

### Contratista

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nombre | string(150) | Nombre o empresa |
| especialidad | string(100) nullable | Texto libre (a diferencia de la especialidad de Técnico, que sí es catálogo fijo — spec 004) |
| telefono | string(20) nullable | |
| correo | string(150) nullable | |
| estado | enum('activo','inactivo') default 'activo' | |
| timestamps | | |

**Nota de diseño**: no se crea tabla de "tarifas" separada en este módulo — el valor/tarifa de un
Contratista en una OT específica se registra en la tabla intermedia de spec 002 (`ot_mano_obra_contratista`),
no aquí, porque puede variar por proyecto.

## Verificación end-to-end

1. `php artisan migrate` corre sin errores; `php artisan db:seed --class=CatalogosMaestrosSeeder` carga
   datos de ejemplo.
2. Como Administrador: crear un Cliente, un Proveedor y un Contratista desde sus respectivas pantallas;
   verificar que quedan disponibles en los selectores del spec 002 (al crear OT) una vez ese módulo exista.
3. Ejecutar `php artisan test --filter=Catalogos` — feature tests de creación, edición, activo/inactivo y
   advertencia de duplicado por NIT/correo, en verde.
4. Verificar mediante policy test que un usuario con rol Técnico no puede acceder a `/clientes` (403).
