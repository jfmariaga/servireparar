# Feature Specification: Gestión de Inventario (Bodega)

**Feature Branch**: `003-inventario-bodega`

**Created**: 2026-08-24

**Status**: Draft

**Input**: Cotización SERVIREPARAR, Módulo 2 — Gestión de Inventario (Bodega); Fase 2 del cronograma;
Mockups Ilustraciones 17-18 (Flujo #3, Almacenista); ER: `INVENTARIO`, `MOVIMIENTOS_INVENTARIO`.
Complementado con el "Manual de Organización y Mapa de Procesos — Almacén Taller SERVIREPARAR" (Cartagena,
2026-09-25) y el Excel real de inventario (`INVENTARIO SERVIREPARAR.xlsx`, hojas: Llantas, EPP, Tuberías y
Láminas, Insumos, Precios, Pinturas, Herramientas, Repuestos), documentos operativos reales provistos por
el cliente.

## Clarifications

### Session 2026-08-24

- Q: ¿Con qué periodicidad y quién es responsable de las auditorías de conteo físico vs. sistema? → A: Sin
  periodicidad fija; se inician bajo demanda, a criterio operativo del taller, sin recordatorio automático
  obligatorio.
- Q: Cuando el conteo físico difiere del stock en sistema, ¿quién puede aprobar el ajuste? → A: Requiere
  aprobación del Administrador; el Almacenista registra el conteo pero el ajuste queda pendiente hasta que
  el Administrador lo apruebe explícitamente (doble validación).

### Session 2026-08-24 (validación contra documentos operativos reales)

- Q: El manual de almacén recomienda auditoría mensual; ¿se corrige la periodicidad "bajo demanda" ya
  decidida? → A: No — se mantiene "bajo demanda, sin periodicidad fija"; la recomendación mensual del
  manual se toma como buena práctica operativa, no como requisito estricto del sistema.
- Q: El Excel real de inventario usa 7 categorías (Llantas, EPP, Tuberías y Láminas, Insumos, Pinturas,
  Herramientas, Repuestos) en vez del tipo binario Herramienta/Consumible. ¿Se adoptan como base del
  catálogo de categorías? → A: Sí — el catálogo de categorías se siembra con estas 7 categorías reales,
  manteniendo Herramienta/Consumible como un atributo aparte (reutilizable o no) dentro de cada categoría.
- Q: El manual pide códigos de barra Code128 por ítem para agilizar entradas/salidas. ¿Se incluye en el
  alcance? → A: Sí — el sistema genera e imprime el código (basado en el código interno del ítem) y admite
  lectura vía lector USB tipo teclado (sin hardware/SDK especial).

### Session 2026-08-25

- Q: Categoría de Inventario y Unidad de Medida — ¿catálogos sembrados una sola vez, o el Almacenista/
  Administrador debe poder mantenerlos desde la interfaz? → A: Desde la interfaz, por la misma razón que
  la especialidad de técnico (spec 004): un catálogo editable solo por seeder es el riesgo que se quería
  evitar (categorías o unidades de medida con nombres distintos para lo mismo por error de digitación).
  `unidad_medida` deja de ser texto libre y pasa a ser un catálogo propio (`UNIDADES_MEDIDA`) con la misma
  pantalla de gestión que Categoría. Inactivar una categoría o unidad no afecta a los ítems que ya la usan,
  solo deja de ofrecerse para ítems nuevos.

### Session 2026-08-25 (costeo de entradas, cancelación de auditoría y dashboard)

- Q: Si una herramienta/consumible ya tiene stock a un costo y llega una nueva entrada a un costo distinto,
  ¿cómo se mantiene el costo real para que spec 002 pueda costear una OT correctamente? → A (dos rechazos
  antes de la decisión final, el mismo día): (1) se probó un costeo por promedio ponderado sobre un único
  campo del maestro — el usuario lo rechazó porque diluye el precio real pagado en cada compra; (2) se
  probó reemplazar ese único campo por el costo de la entrada más reciente — el usuario también lo rechazó,
  porque sigue perdiendo la trazabilidad de lo que costó cada entrada anterior y no sirve para saber el
  costo exacto de lo que realmente se consume en una OT. Decisión final: **costeo por lotes con consumo
  FIFO**. Cada movimiento de tipo entrada es su propio lote (cantidad + costo real de esa compra,
  `cantidad_disponible` que se va descontando). Una salida consume primero el lote más antiguo con saldo
  disponible; si cruza más de un lote, el costo de esa salida es el promedio ponderado únicamente de los
  lotes que efectivamente salieron (no de todo el inventario, no del último precio). El campo
  `Inventario.costo_unitario` queda solo como referencia rápida del costo de la última entrada — el valor
  real del ítem (`valorTotal()`) se calcula sumando sus lotes con saldo, nunca ese campo.
- Q: ¿El costo unitario del ítem se sigue editando a mano en el catálogo? → A: No — se deriva únicamente de
  sus entradas de compra (ver punto anterior); editarlo a mano lo desincronizaría de esa trazabilidad. El
  campo se retiró del formulario de catálogo (junto con la leyenda explicativa que tenía — sobraba). El
  listado de catálogo muestra en su lugar el valor total del ítem, calculado por lotes, que es lo útil de
  ver ítem a ítem (ver spec 009, FR-005).
- Q: ¿Qué pasa si una auditoría se inicia por error y hay que descartarla? → A: Se agrega "Cancelar
  auditoría", distinta de "Cerrar": la auditoría queda en estado `cancelada` (no `cerrada`) y cualquier
  ajuste que estuviera pendiente de aprobación queda automáticamente `rechazado` (con el motivo anotado),
  sin tocar el `stock_actual`.
- Q: ¿Cómo se ve "de un vistazo" el estado del almacén? → A: Un dashboard propio (`/inventario`, antes
  ocupado por el catálogo) con tarjetas de ítems activos, valor real del inventario, ítems bajo stock
  mínimo y herramientas por estado, más una tabla de ítems más críticos por stock bajo y valor por
  categoría. El catálogo se movió a `/inventario/catalogo`.

### Session 2026-09-01 (canal de venta sin OT: vendedor → almacén → remisión firmada)

- Q: La "solicitud manual sin OT" (US2) hoy es un formulario de un solo paso del Almacenista que registra
  y descuenta la salida de inmediato. El proceso real tiene tres vendedores que reciben la solicitud del
  cliente, validan contra inventario y piden despacho al almacén, que entrega contra una remisión firmada.
  ¿Se adopta ese proceso? → A: Sí. Se agrega la User Story 6: el Vendedor crea una **solicitud de
  despacho** con estados; el Almacenista la recibe, remisiona y entrega. El formulario de un solo paso de
  US2 se reemplaza por este flujo como canal estándar sin OT; los movimientos históricos con
  `origen: manual` siguen siendo válidos y el canal nuevo usa `origen: despacho`.
- Q: ¿"Vendedor" es un rol nuevo del sistema? → A: Sí — quinto rol, se agrega en
  [spec 001](../001-autenticacion-usuarios/spec.md). Puede consultar el catálogo de inventario y
  crear/editar/anular solicitudes de despacho; no descuenta stock, no gestiona catálogos ni auditorías.
- Q: ¿Cómo se modela la solicitud del vendedor al almacén? → A: Entidad propia (`SOLICITUDES_DESPACHO` +
  detalle) con máquina de estados `borrador` / `solicitada` / `recibida` / `remisionada` / `entregada` /
  `anulada`, separada de `MOVIMIENTOS_INVENTARIO`; el movimiento de salida se genera solo al confirmar la
  entrega firmada.
- Q: ¿Cómo se maneja la remisión que firma el cliente? → A: El sistema genera una Remisión de Entrega con
  consecutivo `REM-####` y captura la **firma digital del cliente en pantalla** (trazo sobre lienzo), con
  nombre y documento de quien recibe; la firma queda embebida en la remisión imprimible.
- Q: Cuando el producto no está en el almacén y se compra a un externo, ¿cómo queda la trazabilidad? → A:
  Registro simple en la línea de la solicitud (proveedor externo, costo, motivo fijo "No disponible en
  almacén"), SIN pasar por el inventario (no se crea ítem, ni lote, ni movimiento). El enlace formal con
  Compras (spec 006) queda fuera de alcance de este canal.

### Session 2026-09-01 (revisión visual contra la remisión física real "REMISIÓN BAQ Nº ####")

- Q: ¿Qué campos faltaban en la remisión digital frente al formato físico del taller? → A: Se agregan a la
  remisión: el **logo** de SERVIREPARAR (embebido, en vez del texto "SERVIREPARAR / S.A.S — Taller de
  servicios"), datos del cliente (dirección, NIT, teléfono, correo — ya viven en spec 000, solo se
  muestran), nombre de quien entrega físicamente (`entregado_por_nombre`, distinto de quien genera la
  remisión) y una nota libre de entrega (`nota_entrega` — la narración tipo "Se realiza la entrega de 2
  ventiladores en buen estado al señor…").
- Q: ¿Cómo se identifica la ciudad de la remisión? → A: Las remisiones se hacen **por ciudad**, rotuladas
  con la sigla aeroportuaria (IATA) — ej. "REMISIÓN BAQ" para Barranquilla. La sede se elige al crear la
  solicitud de despacho (campo `sede` en `SOLICITUDES_DESPACHO`), de una lista configurable
  (`config/despachos.php` → `sedes`); `sede_por_defecto` (env `DESPACHO_SEDE`) fija la preselección. El PDF
  muestra "REMISIÓN {sigla}" y el nombre de la ciudad.
- Q: ¿La distinción inventario / compra externa aparece en la remisión que ve el cliente? → A: No. Esa
  separación es **trazabilidad interna**; el PDF del cliente lista todos los artículos juntos bajo
  "Despachamos a ustedes los siguientes artículos", sin la etiqueta "No disponible en almacén", sin el
  proveedor externo y sin costos. La separación se mantiene solo en la pantalla interna de despacho.
- Q: ¿Qué pasa tras confirmar la entrega recibida a satisfacción? → A: El sistema envía automáticamente una
  copia del PDF de la remisión al correo del cliente (spec 000, `Cliente.correo`). Si el cliente no tiene
  correo registrado, la entrega se confirma igual y se avisa que no se envió copia; un fallo de correo no
  revierte la entrega.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Atender solicitudes de insumos generadas desde una OT (Priority: P1)

El Almacenista revisa las solicitudes de insumos generadas automáticamente por las tareas de una OT
(spec 002), las aprueba y registra la entrega física al técnico responsable.

**Why this priority**: Es el punto de integración crítico entre Bodega y el módulo central de OT; sin esto
las tareas de OT no pueden ejecutarse con los insumos correctos.

**Independent Test**: Con una solicitud de insumo pendiente proveniente de una OT, el Almacenista la
aprueba y marca como entregada, y se verifica el descuento de stock correspondiente.

**Acceptance Scenarios**:

1. **Given** una solicitud de insumo pendiente originada por una tarea de OT, **When** el Almacenista la
   aprueba, **Then** la solicitud cambia a estado "Aprobada" y queda disponible para entrega
   (Ilustración 17).
2. **Given** una solicitud aprobada, **When** el Almacenista marca "Entregar", **Then** el sistema registra
   el movimiento de salida, descuenta stock si es consumible, y marca la herramienta como "En uso" si
   aplica.
3. **Given** una solicitud de insumo cuyo stock disponible es insuficiente, **When** el Almacenista intenta
   aprobarla/entregarla, **Then** el sistema bloquea o advierte la insuficiencia de stock.

---

### User Story 2 - Registrar solicitud manual de insumos sin OT (Priority: P1)

El Almacenista registra una salida directa de insumos hacia un cliente externo, sin que exista una Orden de
Trabajo asociada, manteniendo su propia trazabilidad de costos.

**Why this priority**: Es la "funcionalidad diferencial" explícita de la cotización (consumo de repuestos
para externos sin OT); representa un canal de ingreso/control de costos independiente.

**Independent Test**: Se registra una solicitud manual seleccionando cliente, insumo y cantidad, y se
verifica que se descuenta del stock y queda trazada como "Manual", separada de las solicitudes vía OT.

**Acceptance Scenarios**:

1. **Given** el Almacenista autenticado, **When** completa el formulario de "Solicitud de Insumo" manual
   con cliente, insumo y cantidad (Ilustración 18), **Then** el sistema registra la salida directa, sin
   requerir una OT asociada.
2. **Given** una solicitud manual registrada, **When** se consulta el historial de movimientos, **Then**
   queda claramente distinguible de las solicitudes originadas desde OT, con su costo asociado.

> **Nota (2026-09-01)**: el formulario de un solo paso de esta historia se reemplaza por el flujo de la
> User Story 6 (Vendedor → solicitud de despacho → remisión firmada) como canal estándar sin OT. Los
> movimientos históricos con `origen: manual` siguen siendo válidos.

---

### User Story 3 - Devolución y control de estado de herramientas (Priority: P2)

Al finalizar el uso, el Almacenista registra la devolución de herramientas, evaluando su estado
(disponible, dañada, requiere mantenimiento).

**Why this priority**: Cierra el ciclo de vida de las herramientas reutilizables, evitando pérdida de
trazabilidad de activos de la empresa.

**Independent Test**: Se marca una herramienta "En uso" como devuelta, seleccionando su nuevo estado, y se
verifica que vuelve a estar disponible (o pasa a "Dañada"/"En mantenimiento" según corresponda).

**Acceptance Scenarios**:

1. **Given** una herramienta en estado "En uso" asociada a una OT finalizada, **When** el Almacenista
   registra su devolución en buen estado, **Then** la herramienta pasa a "Disponible" y queda libre para
   nueva asignación.
2. **Given** una herramienta devuelta en mal estado, **When** el Almacenista la marca como "Dañada" o "En
   mantenimiento", **Then** la herramienta queda excluida de nuevas asignaciones hasta cambiar de estado
   manualmente.

---

### User Story 4 - Alertas de stock mínimo y auditoría de inventario (Priority: P2)

El sistema alerta automáticamente cuando un consumible llega a su stock mínimo, y permite registrar
auditorías periódicas de conteo físico vs. sistema con ajustes controlados.

**Why this priority**: Previene desabastecimiento operativo y sostiene la confiabilidad del inventario en
el tiempo — explícitamente descrito como "Auditoría" en la cotización.

**Independent Test**: Se reduce el stock de un consumible por debajo de su `stock_minimo` mediante un
movimiento de salida y se verifica que se genera la alerta correspondiente (ver spec 008).

**Acceptance Scenarios**:

1. **Given** un consumible cuyo `stock_actual` cae por debajo de `stock_minimo` tras un movimiento de
   salida, **When** se guarda el movimiento, **Then** el sistema dispara una alerta de stock bajo.
2. **Given** un proceso de auditoría iniciado, **When** el Almacenista registra el conteo físico y difiere
   del stock en sistema, **Then** el sistema registra el ajuste propuesto en estado "Pendiente de
   aprobación" con el motivo, sin modificar aún el `stock_actual`.
3. **Given** un ajuste de auditoría pendiente, **When** el Administrador lo aprueba, **Then** el sistema
   aplica el ajuste al `stock_actual` y lo deja en el historial de la auditoría; si lo rechaza, el stock no
   se modifica y queda registrado el motivo del rechazo.

---

### User Story 5 - Codificación, ubicación física y código de barras (Priority: P2)

Cada ítem de inventario tiene una categoría (del catálogo real: Llantas, EPP, Tuberías y Láminas, Insumos,
Pinturas, Herramientas, Repuestos), un código interno con prefijo por tipo, una ubicación física
(Pasillo-Estante-Nivel) y un código de barras generado por el sistema para agilizar sus movimientos.

**Why this priority**: Es el esquema de organización física y de codificación que el almacén ya usa en la
práctica (Manual de Almacén); digitalizarlo reduce tiempos de búsqueda y errores de digitación en entradas/
salidas.

**Independent Test**: Se registra un ítem nuevo de categoría "Repuestos", se le asigna ubicación
`A-01-01`, se verifica que el sistema genera su código interno con prefijo `REP-` y un código de barras
imprimible, y que ese código puede usarse para buscar el ítem mediante un lector USB.

**Acceptance Scenarios**:

1. **Given** un ítem nuevo, **When** se registra seleccionando su categoría del catálogo (Llantas, EPP,
   Tuberías y Láminas, Insumos, Pinturas, Herramientas, Repuestos), **Then** el sistema asigna
   automáticamente un código interno con el prefijo correspondiente al tipo (`REP-`, `HER-`, `CON-`,
   `ACC-`) y un consecutivo único.
2. **Given** un ítem con código interno asignado, **When** se registra su ubicación física, **Then** el
   sistema valida el formato `PASILLO-ESTANTE-NIVEL` (ej. `A-01-01`).
3. **Given** un ítem registrado, **When** el Almacenista solicita su etiqueta, **Then** el sistema genera un
   código de barras Code128 imprimible basado en su código interno.
4. **Given** un lector de código de barras USB (modo teclado) conectado, **When** se escanea el código de
   un ítem en un formulario de movimiento, **Then** el sistema identifica el ítem automáticamente sin
   necesidad de búsqueda manual.

---

### User Story 6 - Venta mostrador: despacho sin OT con remisión firmada (Priority: P1)

Un Vendedor recibe la solicitud de compra de un cliente, valida contra el inventario qué ítems existen, y
genera una **solicitud de despacho** al almacén. El Almacenista la recibe, prepara los ítems y, al
entregarlos, genera una **remisión de entrega** que el cliente **firma digitalmente en pantalla**. Los
ítems que el almacén no tiene se marcan para **compra externa**, dejando registrada la trazabilidad de que
no se contaba con ese insumo o herramienta.

**Why this priority**: Es el canal comercial de salida de mercancía sin Orden de Trabajo (la "funcionalidad
diferencial" de la cotización); formaliza el proceso real de los tres vendedores → almacén → cliente, con
separación de responsabilidades y evidencia de entrega.

**Independent Test**: Un Vendedor crea una solicitud de despacho con dos líneas (una de un ítem en stock,
otra de un ítem que el almacén no tiene, marcada como compra externa); el Almacenista la recibe, genera la
remisión, captura la firma del cliente y confirma la entrega. Se verifica que solo la línea en stock generó
movimiento de salida y descuento, que la línea externa quedó trazada como "No disponible en almacén" sin
tocar el inventario, y que la remisión firmada queda consultable.

**Acceptance Scenarios**:

1. **Given** un Vendedor autenticado, **When** registra una solicitud de despacho seleccionando cliente y
   agregando líneas (ítem del catálogo + cantidad, o descripción libre para lo que el almacén no tiene),
   **Then** el sistema clasifica cada línea como "de inventario" (ítem existente y activo) o "compra
   externa" y crea la solicitud con consecutivo `SD-####` en estado "Solicitada".
2. **Given** una solicitud de despacho en estado "Solicitada", **When** el Almacenista la abre, **Then** la
   marca como "Recibida" y ve las líneas separadas en "a despachar de inventario" y "a comprar por fuera".
3. **Given** una solicitud "Recibida", **When** el Almacenista confirma la preparación y genera la
   remisión, **Then** el sistema crea una Remisión de Entrega con consecutivo `REM-####` que lista todas
   las líneas, y la solicitud pasa a "Remisionada".
4. **Given** una remisión generada, **When** quien entrega y el cliente firman digitalmente en pantalla
   (con el nombre de quien entrega y el nombre y documento de quien recibe) y el Almacenista confirma la
   entrega, **Then** el sistema registra un movimiento de salida (`origen: despacho`) por cada línea de
   inventario —descontando stock y aplicando
   costeo FIFO (FR-016)—, deja las líneas de compra externa solo como registro trazable (sin movimiento ni
   lote), y la solicitud pasa a "Entregada".
5. **Given** una línea marcada como compra externa, **When** se registra, **Then** el sistema guarda
   proveedor externo, costo y el motivo fijo "No disponible en almacén", sin crear ítem de catálogo ni
   lote de inventario.
6. **Given** una solicitud de despacho en cualquier estado previo a "Entregada", **When** el Vendedor o el
   Almacenista la anula, **Then** pasa a "Anulada" sin haber afectado el `stock_actual`.
7. **Given** una línea de inventario cuyo stock disponible ya no alcanza al momento de confirmar la
   entrega, **When** el Almacenista intenta confirmarla, **Then** el sistema bloquea la entrega de esa
   línea e informa la insuficiencia (FR-009), permitiendo re-marcarla como compra externa o ajustar la
   cantidad.
8. **Given** una solicitud con líneas de inventario y líneas de compra externa a la vez, **When** el
   Almacenista la abre, **Then** el sistema muestra ambos grupos claramente separados y rotulados
   («Despachar de bodega» vs «Compra externa — no disponible en almacén»), para que sea inequívoco qué se
   entrega desde el almacén y qué se gestiona por fuera.
9. **Given** un Almacenista con solicitudes pendientes de recibir, remisionar o entregar, **When** navega
   el sistema, **Then** ve un indicador visible (contador en el menú y aviso en la bandeja de despachos)
   de que tiene trabajo pendiente en este canal.
10. **Given** una entrega confirmada con firma y el cliente tiene correo registrado, **When** el Almacenista
    la confirma, **Then** el sistema envía automáticamente una copia del PDF de la remisión a ese correo y
    lo deja registrado (`enviada_al_cliente_en`); si el cliente no tiene correo, la entrega se confirma
    igual y el sistema avisa que no se envió copia.
11. **Given** una remisión de un despacho con líneas de inventario y de compra externa, **When** se genera
    su PDF (para descarga o para el correo al cliente), **Then** el documento lista todos los artículos en
    una sola tabla ("Despachamos a ustedes los siguientes artículos"), con los datos del cliente, "Entrega"
    y "Recibe" con firma, y SIN exponer la etiqueta "No disponible en almacén", el proveedor externo ni
    costos (trazabilidad interna).

### Edge Cases

- ¿Qué ocurre si dos solicitudes concurrentes intentan reservar el mismo stock limitado de un consumible?
  Debe evitarse sobregiro de stock (descuentos atómicos).
- ¿Puede un ítem del catálogo cambiar de categoría (herramienta ↔ consumible) una vez tiene movimientos
  históricos? Debería restringirse o requerir justificación.
- Periodicidad y responsable de las auditorías de conteo físico: sin periodicidad fija; el Almacenista las
  inicia bajo demanda (ver Clarifications).
- Aprobación de ajustes de auditoría: requiere doble validación — el Almacenista registra el conteo, el
  Administrador aprueba antes de que impacte el `stock_actual` (ver Clarifications).
- ¿Qué pasa si dos ítems distintos quedan asignados a la misma ubicación física por error? El sistema debe
  advertir la colisión de ubicación al momento de asignarla, sin bloquear (puede haber más de un ítem por
  nivel/estante en la práctica).
- Categorías y prefijos de código no son exhaustivos del Excel real (ej. "Precios" y "Pinturas" no tienen
  prefijo propio documentado) — se normalizan en `/speckit-plan` mapeando cada categoría real a uno de los
  4 prefijos base (`REP-`, `HER-`, `CON-`, `ACC-`) según corresponda.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE clasificar cada ítem del inventario en una categoría de un catálogo
  configurable, sembrado inicialmente con las categorías reales del taller (Llantas, EPP, Tuberías y
  Láminas, Insumos, Pinturas, Herramientas, Repuestos), manteniendo además el atributo Herramienta
  (reutilizable) / Consumible (uso único) independiente de la categoría.
- **FR-002**: El sistema DEBE gestionar el ciclo de estado de las herramientas (Disponible, En uso, Dañada,
  En mantenimiento) y su historial de uso.
- **FR-003**: El sistema DEBE descontar automáticamente el stock de consumibles al registrar una salida
  (vía OT o manual).
- **FR-004**: El sistema DEBE generar automáticamente solicitudes de insumo en Bodega a partir de las
  tareas de una OT que requieran herramientas/consumibles (integración con spec 002).
- **FR-005**: El sistema DEBE permitir registrar solicitudes manuales de salida de insumos hacia clientes
  externos, sin OT asociada, con su propia trazabilidad de costos.
- **FR-006**: El sistema DEBE registrar todo movimiento de inventario (`MOVIMIENTOS_INVENTARIO`: entrada,
  salida, devolución) con tipo, cantidad, fecha, motivo/referencia y usuario responsable.
- **FR-007**: El sistema DEBE generar una alerta cuando el `stock_actual` de un consumible caiga por debajo
  de su `stock_minimo`.
- **FR-008**: El sistema DEBE permitir al Almacenista iniciar un proceso de auditoría (conteo físico vs.
  sistema) en cualquier momento, sin periodicidad obligatoria, registrando los ajustes propuestos con
  motivo.
- **FR-008a**: Todo ajuste propuesto en una auditoría DEBE quedar en estado "Pendiente de aprobación" y
  requerir aprobación explícita del Administrador antes de modificar el `stock_actual`; el sistema DEBE
  conservar el historial completo (propuesto, aprobado/rechazado, por quién y cuándo).
- **FR-009**: El sistema DEBE impedir que una solicitud de insumo se apruebe/entregue si el stock
  disponible es insuficiente.
- **FR-010**: El sistema DEBE mantener asignación y responsable de cada herramienta prestada mientras esté
  en estado "En uso".
- **FR-011**: El sistema DEBE asignar automáticamente a cada ítem nuevo un código interno con prefijo por
  tipo (`REP-`, `HER-`, `CON-`, `ACC-`) y un consecutivo único.
- **FR-012**: El sistema DEBE permitir registrar la ubicación física de cada ítem en formato
  `PASILLO-ESTANTE-NIVEL` (ej. `A-01-01`), advirtiendo (sin bloquear) si dos ítems distintos comparten
  ubicación.
- **FR-013**: El sistema DEBE generar e imprimir un código de barras Code128 por ítem, basado en su código
  interno, y DEBE permitir identificar un ítem en formularios de movimiento mediante lectura de ese código
  con un lector USB en modo teclado (sin requerir hardware o SDK especial).
- **FR-014**: Los movimientos de entrada de inventario DEBEN registrar el Proveedor (spec 000) que
  suministró el ítem.
- **FR-015**: El sistema DEBE permitir crear, editar e inactivar categorías de inventario y unidades de
  medida desde una pantalla propia (no un seeder ni intervención técnica), evitando nombres distintos para
  lo mismo por error de digitación. Inactivar una categoría o unidad NO DEBE afectar a los ítems que ya la
  usan; solo deja de ofrecerse como opción para ítems nuevos.
- **FR-016**: El sistema DEBE llevar el costeo de inventario por lotes con consumo FIFO: cada movimiento de
  entrada es su propio lote (cantidad y costo real de esa compra, con una cantidad disponible que se
  descuenta a medida que se consume). Una salida DEBE consumir primero el lote más antiguo con saldo
  disponible; si una salida cruza más de un lote, su costo unitario registrado DEBE ser el promedio
  ponderado únicamente de los lotes efectivamente consumidos en esa salida (nunca un promedio de todo el
  inventario, ni el costo de la compra más reciente aplicado a stock que costó distinto). Un ajuste de
  auditoría (FR-008a) que reduzca el stock DEBE consumir lotes de la misma forma; uno que lo aumente DEBE
  crear un lote nuevo al costo de referencia del ítem. El costo unitario del ítem (`Inventario.costo_unitario`)
  es solo una referencia informativa del costo de su última entrada — NO se edita manualmente desde el
  catálogo y NO es la fuente del valor total del ítem (ver FR-018).
- **FR-017**: El sistema DEBE permitir cancelar una auditoría abierta (distinto de cerrarla): la auditoría
  queda en estado `cancelada` y todo ajuste que estuviera pendiente de aprobación en ella queda
  automáticamente `rechazado`, sin modificar el `stock_actual`.
- **FR-018**: El sistema DEBE ofrecer un panel/dashboard de inventario con el valor real del inventario
  (Σ del valor de los lotes con saldo disponible de cada ítem, ver FR-016), conteo de ítems bajo su stock
  mínimo, estado de herramientas (disponibles/en uso/dañadas) y valor por categoría, como vista de entrada
  al módulo.
- **FR-019**: El sistema DEBE permitir a un Vendedor (spec 001, rol nuevo) registrar una **solicitud de
  despacho** (canal de venta sin OT) con cliente y una o más líneas, cada una referida a un ítem del
  catálogo con cantidad, o descrita en texto libre cuando el almacén no dispone del ítem. La solicitud DEBE
  tener consecutivo propio (`SD-####`) y un estado en el conjunto: `borrador`, `solicitada`, `recibida`,
  `remisionada`, `entregada`, `anulada`.
- **FR-020**: Al crear o editar una solicitud de despacho, el sistema DEBE clasificar cada línea como "de
  inventario" (ítem existente y activo) o "compra externa", y DEBE permitir al Vendedor forzar una línea a
  "compra externa" aunque el ítem exista en el catálogo.
- **FR-021**: El sistema DEBE registrar, para cada línea de compra externa, el proveedor externo (texto
  libre), el costo y el motivo fijo "No disponible en almacén", SIN crear un ítem de catálogo, SIN crear un
  lote de inventario y SIN generar movimiento de inventario.
- **FR-022**: El Almacenista DEBE poder hacer avanzar la solicitud de despacho por sus estados
  (`solicitada` → `recibida` → `remisionada` → `entregada`); el Vendedor o el Almacenista DEBEN poder
  anularla en cualquier estado previo a `entregada`. Ninguna transición previa a `entregada` afecta el
  `stock_actual`.
- **FR-023**: Al generar la remisión, el sistema DEBE crear una **Remisión de Entrega** con consecutivo
  propio (`REM-####`), asociada 1:1 a la solicitud de despacho, que lista todas las líneas (de inventario y
  de compra externa) y es imprimible.
- **FR-024**: La confirmación de entrega DEBE requerir **dos firmas digitales capturadas en pantalla**
  (trazo sobre lienzo): la de **quien entrega** (`firma_entrega`) y la de **quien recibe** (`firma`),
  junto con el nombre de quien entrega y el nombre y documento de quien recibe; ambas firmas quedan
  embebidas en la Remisión de Entrega. Al confirmar, el sistema DEBE generar un movimiento de salida
  `origen: despacho` por cada línea de inventario (descontando stock y aplicando costeo FIFO, FR-016) y
  DEBE bloquear la confirmación de cualquier línea de inventario sin stock disponible suficiente (FR-009).
- **FR-025**: Las solicitudes de despacho y sus remisiones DEBEN quedar consultables en el historial,
  distinguibles de las salidas por OT (spec 002) y de las salidas manuales directas previas
  (`origen: manual`); el canal nuevo usa `origen: despacho`.
- **FR-026**: La Remisión de Entrega DEBE registrar el nombre de quien entrega físicamente
  (`entregado_por_nombre`) y una nota libre de entrega (`nota_entrega`), y su PDF DEBE reproducir el
  formato de la remisión física del taller: encabezado con el **logo** de SERVIREPARAR (imagen embebida),
  el título "REMISIÓN {sigla IATA de la ciudad}" (de `SolicitudDespacho.sede`) y el consecutivo, datos del
  cliente (nombre, dirección, NIT, teléfono, correo — de spec 000), una única tabla de artículos entregados
  (Cant. / Referencia / Descripción), la nota de entrega, y los bloques "Entrega" y "Recibe" (nombre +
  documento) con la firma. El PDF NO DEBE exponer la clasificación interna de líneas (etiqueta "No
  disponible en almacén", proveedor externo) ni costos.
- **FR-028**: Cada solicitud de despacho DEBE pertenecer a una ciudad/sede, identificada por su sigla
  aeroportuaria (IATA), elegida al crearla de una lista configurable (`config/despachos.php`). La sede
  acompaña al título de la remisión ("REMISIÓN BAQ") y es visible en la bandeja y en la vista de despacho.
- **FR-027**: Al confirmar una entrega recibida a satisfacción, el sistema DEBE enviar automáticamente una
  copia del PDF de la remisión al correo del cliente (`Cliente.correo`, spec 000) y registrar el envío
  (`enviada_al_cliente_en`). Si el cliente no tiene correo, la entrega se confirma igual y el sistema lo
  informa; un fallo en el envío de correo NO DEBE revertir la entrega ya confirmada.

### Key Entities

- **Inventario** (`INVENTARIO`): id, código (con prefijo por tipo), nombre, tipo (Herramienta/Consumible),
  categoria_id, ubicacion (Pasillo-Estante-Nivel), codigo_barras, stock_actual, stock_minimo,
  unidad_medida_id (FK a `UNIDADES_MEDIDA`, ver FR-015), costo_unitario (solo referencia informativa de la
  última entrada, ver FR-016 — no editable a mano, no es la fuente del valor total), activo.
- **Unidad de Medida** (`UNIDADES_MEDIDA`): catálogo configurable — id, nombre, abreviatura, activo (ver
  FR-015).
- **Categoría de Inventario**: catálogo configurable — Llantas, EPP, Tuberías y Láminas, Insumos, Pinturas,
  Herramientas, Repuestos (semilla inicial basada en el Excel real).
- **Movimiento de Inventario** (`MOVIMIENTOS_INVENTARIO`): id, inventario_id, tipo_mov (Entrada/Salida/
  Devolución), cantidad, cantidad_disponible (solo en entradas — saldo del lote sin consumir, ver FR-016),
  costo_unitario (en entradas: costo real de ese lote; en salidas: costo exacto de los lotes consumidos por
  esa salida), fecha, motivo, referencia, usuario_id, proveedor_id (spec 000, en entradas).
- **Auditoría de Inventario** (`AUDITORIAS_INVENTARIO`): id, iniciada_por, fecha_inicio, fecha_cierre,
  estado (`abierta` / `cerrada` / `cancelada`, ver FR-017).
- **Solicitud de Despacho** (`SOLICITUDES_DESPACHO`): id, numero (`SD-####`), cliente_id (spec 000),
  vendedor_id (usuario con rol Vendedor), sede (sigla IATA de la ciudad, FR-028), estado (`borrador` /
  `solicitada` / `recibida` / `remisionada` / `entregada` / `anulada`), observaciones, fecha_solicitud,
  recibida_por, remisionada_por, entregada_en. Canal de salida sin OT (US6), distinto de las salidas por
  OT (spec 002) y de las salidas manuales directas previas.
- **Detalle de Solicitud de Despacho** (`DETALLE_SOLICITUD_DESPACHO`): id, solicitud_id, origen
  (`inventario` / `compra_externa`), inventario_id (nulo si compra externa), descripcion (texto libre para
  compra externa), cantidad, costo_unitario (referencia), proveedor_externo (solo compra externa),
  costo_compra_externa (solo compra externa), motivo (fijo "No disponible en almacén" en compra externa).
- **Remisión de Entrega** (`REMISIONES_ENTREGA`): id, numero (`REM-####`), solicitud_id (1:1),
  generada_por, entregado_por_nombre (quien entrega físicamente, FR-026), fecha, recibido_por_nombre,
  recibido_por_documento, firma (trazo de quien recibe), firma_entrega (trazo de quien entrega, FR-024),
  nota_entrega (narración libre, FR-026), entregada_en,
  enviada_al_cliente_en (marca de envío de la copia PDF al correo del cliente,
  FR-027). Documento imprimible que respalda la entrega física (US6) y reproduce el formato de la remisión
  física del taller.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de las salidas de inventario (vía OT o manuales) quedan registradas con responsable y
  fecha, sin excepciones.
- **SC-002**: El sistema nunca permite que el `stock_actual` de un consumible quede negativo.
- **SC-003**: Toda alerta de stock mínimo se genera en el mismo movimiento que la origina (latencia
  percibida < 1 minuto).
- **SC-004**: El 100% de las devoluciones de herramientas quedan con un estado final explícito (Disponible,
  Dañada o En mantenimiento), sin quedar en estado ambiguo "En uso" tras la devolución.
- **SC-005**: La suma de `cantidad_disponible` de los lotes (movimientos de entrada) de un ítem siempre
  coincide con su `stock_actual` — verificable en cualquier momento comparando ambos valores; ninguna
  salida ni ajuste de auditoría rompe esa igualdad.
- **SC-006**: El costo de una salida que consume más de un lote es exactamente el promedio ponderado de los
  lotes que salieron en esa operación (verificable recalculándolo a mano desde los lotes tocados), nunca un
  promedio de todo el inventario ni el costo de la compra más reciente.
- **SC-007**: El 100% de las entregas del canal de despacho sin OT quedan respaldadas por una Remisión de
  Entrega con consecutivo único y firma digital del receptor; ninguna línea de inventario se entrega sin su
  movimiento de salida y su descuento de stock correspondiente, y ninguna línea de compra externa genera
  movimiento ni lote de inventario.

## Assumptions

- El catálogo de categorías de inventario (`categoria_id`) es mantenido por el Administrador/Almacenista y
  se siembra inicialmente con las 7 categorías reales del taller (ver Clarifications).
- El costo unitario de los insumos usados en solicitudes manuales sin OT es el costo real de los lotes
  consumidos por FIFO en esa salida (ver FR-016); si el ítem no tiene lotes registrados (stock cargado
  antes de este costeo), se usa el costo de referencia del maestro como respaldo.
- Las auditorías son un proceso manual asistido por el sistema; la lectura de código de barras (User Story
  5) agiliza el registro de movimientos y conteos, pero no reemplaza el conteo físico humano.
- Los lectores de código de barras se asumen tipo "USB HID" (actúan como teclado), sin necesidad de drivers
  ni SDK propietario — compatible con cualquier formulario web estándar.
