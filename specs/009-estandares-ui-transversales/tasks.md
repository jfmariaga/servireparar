# Tasks: Estándares Transversales de Interfaz (Moneda y Listas Desplegables)

**Input**: `specs/009-estandares-ui-transversales/spec.md`

**Tests**: No aplica un flujo de negocio propio — se verifica por inspección estática (ningún `<select>`
plano, ningún formateo de moneda fuera del helper) más los tests ya existentes de los módulos retrofitados.

## Format: `[ID] [P?] Description`

## Phase 1: Moneda (FR-001, FR-002, FR-005)

- [X] T001 `app/Support/Moneda.php` — helper único `Moneda::cop()` (símbolo `$`, punto de miles, sin
  decimales; `—` para nulo/vacío)
- [X] T002 [P] Inventario: catálogo (columna "Valor total" en vez de costo unitario suelto), dashboard
  (valor real del inventario, valor por categoría), entradas recientes (costo de cada entrada) — todos vía
  `Moneda::cop()`
- [X] T003 [FR-005] Campo "Costo unitario" retirado del formulario de Editar/Nuevo ítem de Inventario — se
  deriva únicamente de las entradas (spec 003, FR-016), nunca editable a mano

## Phase 2: Listas desplegables con buscador (FR-003, FR-004)

- [X] T004 Instalar `tom-select` (npm) — sin dependencia de jQuery, dispara `change` nativo sobre el
  `<select>` que envuelve, compatible con `wire:model`
- [X] T005 `resources/css/select.css` — tema propio de Tom Select con los tokens Tailwind de la app
  (incluye modo oscuro), importado desde `resources/css/app.css`
- [X] T006 `window.ServiopsSelect.mount()` en `resources/js/app.js` — instancia Tom Select sobre un
  `<select>` dado
- [X] T007 `resources/views/components/select.blade.php` (`<x-select>`) — envuelve el `<select>` en
  `wire:ignore` + Alpine `x-init`; prop `reset-key` para forzar remontaje cuando el valor puede cambiar por
  código (ej. abrir un formulario de edición)
- [X] T008 [P] Retrofit de todo `<select>` existente a `<x-select>`: Inventario (catálogo, movimientos/
  entradas, auditoría, categorías y unidades), Usuarios (rol, estado, especialidad), Equipos, Clientes,
  Proveedores, Contratistas — cero `<select>` planos fuera del propio componente

## Phase 2b: Navegación transversal (FR-006 a FR-009)

**Documentado retroactivamente (2026-09-26)**: implementado en el commit `66eb2f4` (2026-09-25) sin pasar
por `/speckit-tasks` en su momento.

- [X] T012 [FR-006] `resources/views/livewire/buscador-global.blade.php` — atajo Ctrl+K/Cmd+K, busca en
  OT/Clientes/Inventario respetando el permiso que ya protege cada listado (`Gate::authorize` por
  categoría), incluido en el layout global.
- [X] T013 [FR-007] `resources/views/components/breadcrumbs.blade.php` (`<x-breadcrumbs>`), usado en
  Especialidades (`Usuarios → Especialidades`) y Configuración.
- [X] T014 [FR-008] `resources/views/components/modal.blade.php` (`<x-modal>`) reutilizable.
- [X] T015 [FR-009] `public/manifest.json` + `public/sw.js` (service worker básico), enlazados desde
  `resources/js/app.js` — instalable como PWA, sin soporte offline.

## Phase 3: Verificación

- [X] T009 `php artisan test` completo en verde (87/87) tras el retrofit
- [X] T010 `npm run build` sin advertencias (se corrigió el orden de `@import` en `app.css`)
- [X] T011 [P] Grep de verificación: sin `<select>` plano fuera de `components/select.blade.php`; sin
  `number_format` de dinero fuera de `Moneda`

## Implementation Strategy

Retrofit transversal ejecutado de una sola vez sobre los módulos ya implementados (000, 001, 003, 004,
005). Las specs que aún no nacen (002, 006, 007, 008) deben construir sus pantallas ya usando `<x-select>`
y `Moneda::cop()` desde el inicio, sin necesidad de un retrofit posterior — ver FR-001 a FR-004 del spec.
