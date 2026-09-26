# Feature Specification: Estándares Transversales de Interfaz (Moneda y Listas Desplegables)

**Feature Branch**: `009-estandares-ui-transversales`

**Created**: 2026-08-25

**Status**: Implementado (retrofit sobre specs 000, 001, 003, 004, 005; aplica también a las que faltan
por construir — 002, 006, 007, 008)

**Input**: Corrección del usuario sobre el formulario "Editar ítem" de Inventario (specs 003): el campo
Costo unitario no debía ser editable allí (el costo se deriva de las entradas de compra, ver spec 003
FR-016) y la columna del listado debía mostrar valor total, no costo unitario suelto.
Junto con esa corrección, el usuario pidió dos reglas de alcance transversal, válidas para todos los
módulos presentes y futuros, no solo Inventario:

1. Todo valor que represente dinero, en cualquier pantalla de cualquier módulo, debe mostrarse formateado
   en pesos colombianos (COP) — nunca como número plano.
2. Toda lista desplegable (`<select>`) de la aplicación debe tener buscador tipo "select2" — no un
   `<select>` HTML plano, sin importar cuántas opciones tenga.

Esta spec no introduce una pantalla nueva: documenta la regla como requisito no funcional transversal,
para que toda spec nueva o pantalla nueva la cumpla por defecto sin tener que pedirla de nuevo.

## Clarifications

### Session 2026-08-25

- Q: ¿Con qué librería se implementan las listas desplegables con buscador, dado que la app usa
  Livewire + Alpine.js sin jQuery (Select2 clásico requiere jQuery)? → A: Tom Select — sin dependencia de
  jQuery, UX equivalente a Select2, se integra con `wire:model` porque dispara el evento `change` nativo
  sobre el `<select>` que envuelve.
- Q: ¿En qué alcance se aplica el buscador en los `<select>`? → A: Toda la aplicación, no solo Inventario
  — cualquier `<select>` de cualquier pantalla, presente o futura.
- Q: El campo "Costo unitario" del formulario "Editar ítem" de Inventario ¿se mantiene editable? → A: No.
  El costo del ítem se deriva exclusivamente de sus lotes de entrada (spec 003 FR-016: costeo por lotes con
  consumo FIFO — cada entrada es su propio lote, cada salida consume el más antiguo); permitir editarlo a
  mano en el catálogo lo dejaría desincronizado de esa trazabilidad. El listado de catálogo, en vez de
  mostrar el costo unitario suelto (poco útil por sí solo, y ya no existe como "el" costo del ítem), muestra
  el valor total del ítem, calculado sumando sus lotes con saldo disponible.

### Session 2026-09-26 (navegación transversal: buscador global, breadcrumbs, modal, PWA — documentado retroactivamente)

> Esta sección documenta funcionalidad que ya estaba construida y en producción (commit `66eb2f4`,
> 2026-09-25) sin haber pasado por `/speckit-clarify` — se registra aquí porque, igual que Moneda y
> `<x-select>`, son componentes de navegación/UX transversales a toda la app, no de un módulo en particular.

- Q: Con varios módulos ya operativos, ¿cómo llega un usuario rápido a una OT, cliente o ítem de inventario
  puntual sin recorrer menús? → A: Buscador global (Ctrl+K / Cmd+K), disponible en todo el layout, que
  busca en OT/Clientes/Inventario respetando el mismo permiso que ya protege cada listado — nadie encuentra
  por ahí algo que no vería en su pantalla normal.
- Q: ¿Las pantallas internas (ej. Especialidades dentro de Usuarios) necesitan indicar dónde están dentro
  de la jerarquía de navegación? → A: Sí — componente `<x-breadcrumbs>` reutilizable, usado en pantallas que
  cuelgan de otra (ej. `Usuarios → Especialidades`).
- Q: ¿Cómo se estandarizan los diálogos de confirmación/formulario emergente entre módulos? → A: Componente
  `<x-modal>` reutilizable en vez de que cada pantalla arme su propio overlay con Alpine desde cero.
- Q: ¿La app debe poder instalarse como aplicación (PWA) en el taller? → A: Sí — `public/manifest.json` +
  `public/sw.js` (service worker básico) para que se pueda "agregar a inicio" desde el navegador, sin que
  esto implique soporte offline completo (fuera de alcance).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Todo valor monetario mostrado en cualquier vista de cualquier módulo (costos, tarifas,
  totales, precios, valores de inventario, etc.) DEBE formatearse como pesos colombianos: símbolo `$`,
  separador de miles con punto, sin decimales (ej. `$ 16.000`), usando el helper único `App\Support\Moneda`
  — nunca un `number_format` o interpolación ad-hoc distinta por pantalla.
- **FR-002**: Ninguna pantalla, presente o futura, debe mostrar un monto en texto plano sin ese formato,
  independientemente del módulo (Inventario, Equipos, Compras/Cotizaciones, Reportes, etc.).
- **FR-003**: Toda lista desplegable de selección única o múltiple en la aplicación DEBE ofrecer búsqueda
  por texto dentro de sus opciones (equivalente a "select2"), implementada con el componente
  `<x-select>` (Tom Select) — no se debe usar un `<select>` HTML sin buscador salvo un caso excepcional
  documentado explícitamente en la spec de esa pantalla.
- **FR-004**: Cuando el valor de un `<x-select>` puede ser fijado por código (por ejemplo, al abrir un
  formulario de edición con datos ya cargados) y no solo por interacción del usuario, la pantalla DEBE
  forzar el remontaje del componente (prop `reset-key` ligada a un valor que cambie en ese caso, como el
  id del registro en edición) para que la lista desplegada refleje el valor real — Livewire no puede
  actualizar el DOM que Tom Select ya transformó (`wire:ignore`).
- **FR-005**: El costo unitario de un ítem de inventario (spec 003) NO se edita manualmente desde el
  catálogo — se deriva únicamente de sus lotes de entrada (FR-016 de spec 003: costeo por lotes, consumo
  FIFO). El listado de catálogo muestra el valor total del ítem (suma de sus lotes con saldo disponible),
  no un costo unitario aislado.
- **FR-006**: El sistema DEBE ofrecer un buscador global (atajo Ctrl+K/Cmd+K, disponible en todo el layout
  autenticado) que permita saltar directo a una OT, cliente o ítem de inventario por texto, respetando en
  cada categoría el mismo permiso que ya protege su listado propio.
- **FR-007**: Toda pantalla que cuelgue de otra en la jerarquía de navegación (ej. un catálogo dentro de
  Usuarios) DEBE mostrar su ruta con el componente reutilizable `<x-breadcrumbs>`, no un título suelto.
- **FR-008**: Los diálogos de confirmación o formulario emergente DEBEN usar el componente reutilizable
  `<x-modal>`, no un overlay armado ad-hoc por pantalla.
- **FR-009**: La aplicación DEBE ser instalable como PWA (manifest + service worker básico), sin que esto
  implique soporte de uso sin conexión.

### Alcance ya cubierto (retrofit, 2026-08-25)

- **Moneda (`App\Support\Moneda::cop()`)**: catálogo e ítems de Inventario (costo unitario retirado del
  listado, reemplazado por valor total), dashboard de Inventario (valor total del inventario y valor por
  categoría), entradas recientes de Inventario (costo de cada entrada).
- **Listas desplegables (`<x-select>` / Tom Select)**: filtros y formularios de Inventario (catálogo,
  movimientos/entradas, auditoría, categorías y unidades), Usuarios (rol, estado, especialidad de
  técnico), Equipos, Clientes, Proveedores y Contratistas.
- Pendiente de nacer con las specs que aún no se implementan (006 Compras/Cotizaciones): debe nacer ya
  cumpliendo FR-001 a FR-004 y FR-006 a FR-008, sin necesidad de un retrofit posterior.
- **Navegación/UX (2026-09-26)**: buscador global (`livewire/buscador-global.blade.php`, FR-006),
  `<x-breadcrumbs>` (FR-007, usado en Especialidades y Configuración), `<x-modal>` (FR-008), PWA
  (`public/manifest.json` + `public/sw.js`, FR-009).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Ningún grep de `number_format` o interpolación monetaria manual aparece fuera de
  `App\Support\Moneda` en el código de vistas/componentes.
- **SC-002**: Ningún `<select>` HTML plano (sin `<x-select>`) aparece en `resources/views/**/*.blade.php`
  fuera del propio componente `resources/views/components/select.blade.php`.
- **SC-003**: Abrir "Editar ítem" en Inventario ya no expone un campo de costo unitario editable.

## Assumptions

- Tom Select no requiere jQuery ni CSS de terceros: el tema visual propio (`resources/css/select.css`) usa
  las mismas clases y colores Tailwind del resto de la app, con soporte de modo oscuro.
- Esta spec no reemplaza el detalle de negocio de cada módulo (por ejemplo, el costeo de spec 003): solo
  fija el estándar de presentación que todas las pantallas deben respetar.
