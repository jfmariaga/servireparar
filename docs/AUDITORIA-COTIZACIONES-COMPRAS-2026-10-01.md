# Auditoría — Cotizaciones y Compras (spec 006)

**Fecha**: 2026-10-01. **Estado**: ambos módulos están construidos y con tests
verdes (código correcto respecto a lo que dice el spec), pero **no están a
nivel profesional para uso real con clientes/proveedores**. Ocultos del menú
en producción (`soloDesarrollo` en `nav-items.blade.php`) por esta razón.

**Pendiente antes de construir nada de esta lista**: pedirle al usuario (dueño
del negocio) el **formato que usan hoy** para una cotización a cliente y una
orden de compra a proveedor (físico o Excel/Word actual). Eso debe confirmar o
corregir varios puntos de la Prioridad 1 y 2 de abajo (impuestos, vigencia,
condiciones, numeración esperada, campos que el taller sí usa y que aquí no
están contemplados).

## Cómo funcionan hoy

### Cotizaciones
Entrada por correo (IMAP, `cotizaciones:procesar-correo` cada 5 min) o
directamente desde la pantalla. Estados:
`en_revision → cotizada → aceptada|rechazada → entregada → facturada`.
El Administrador arma los ítems (servicio o insumo, costo tomado del catálogo),
envía el PDF por correo (con Message-ID propio para rastrear la respuesta del
cliente en el mismo hilo), y puede marcar entregada/facturada manualmente.
Detalle completo del flujo en el audit de abajo.

### Compras
Formulario de creación (`/compras/nueva`) con proveedor + ítems contra el
catálogo de inventario (costo tecleado a mano). Estados lineales:
`recepcion → cotizacion → aprobacion → facturada`, cada transición solo deja
una marca de tiempo, sin bitácora.

## Gaps encontrados (auditoría de código, 2026-10-01)

**Cotizaciones**:
- PDF sin IVA/impuestos, sin fecha de vigencia, sin condiciones de pago ni
  garantía, sin descuentos.
- No hay botón "Rechazar" manual — solo se cierra como rechazada si el cliente
  contesta el correo con una palabra clave exacta.
- No se puede editar/reenviar una cotización ya enviada (contraoferta del
  cliente) — el propio spec.md lo contempla como caso de borde y nunca se
  implementó.
- Campo `equipo_id` en la tabla sin ningún campo en pantalla para usarlo.
- `ImapPollingProveedorCorreo::marcarProcesado()` es un no-op — riesgo de
  reprocesar el mismo backlog en bucle una vez haya un buzón real (nunca
  probado contra IMAP real).
- Solo soporta un buzón de correo monitoreado.

**Compras**:
- Facturar una compra **no mueve el stock de Inventario** — es un registro de
  costos, no un mecanismo real de reabastecimiento (explícito en las
  Assumptions del spec, pero limita el uso real del módulo).
- Sin adjuntar el documento del proveedor (cotización/factura/remisión) — no
  hay subida de archivos en ningún punto del flujo.
- Sin bitácora de comentarios/eventos (a diferencia de Cotizaciones).
- Costo unitario tecleado libremente, sin anclarse al costo del catálogo.
- No hay forma de comparar cotizaciones de varios proveedores para el mismo
  pedido.

**Transversal**:
- Permisos todo-o-nada (`manage-cotizaciones`/`manage-compras`, solo
  Administrador): ver y ejecutar acciones son la misma habilidad.
- IMAP/SMTP reales nunca configurados ni probados en producción.

## Lista priorizada de trabajo

### Prioridad 1 — Bloquea el uso real día a día
1. Botón "Rechazar" manual en Cotizaciones (`cotizada → rechazada` sin
   depender del correo).
2. Compra → Inventario: `facturar()` debe generar el movimiento de entrada
   real (vía `MovimientoService`, mismo patrón que Despachos).
3. Editar/reenviar una cotización ya enviada (volver a `en_revision` desde
   `cotizada` conservando el historial del hilo).
4. IVA en la cotización (campo de impuesto, reflejado en PDF y total).

### Prioridad 2 — El documento/proceso debe verse serio ante cliente/proveedor
5. Vigencia de la cotización ("válida hasta…") en el PDF.
6. Adjuntar documento del proveedor en Compras (reutilizar patrón de
   evidencias de OT).
7. Bitácora de Compras (tabla tipo `mensajes_compra`, igual a
   `mensajes_cotizacion`).
8. Condiciones de pago/garantía en el PDF (aunque sea texto fijo
   configurable).
9. Descuento en la cotización (por cotización o por línea).

### Prioridad 3 — Robustez operativa, el taller puede convivir sin esto un tiempo
10. Vincular `equipo_id` desde la UI de Cotizaciones, o quitarlo si no se va
    a usar.
11. Costo unitario de Compras anclado al catálogo (como ya hace Cotizaciones).
12. Permisos más finos (separar "ver" de "ejecutar acciones").
13. Comparar cotizaciones de varios proveedores para una misma compra — **a
    confirmar con el dueño del negocio si de verdad lo necesita** antes de
    construirlo (es la pieza más grande de toda la lista).

### Prioridad 4 — Infraestructura de correo
14. Configurar y probar IMAP/SMTP reales contra la cuenta de correo oficial.
15. Arreglar el no-op de `marcarProcesado()` — validar si hace falta marcar
    el correo como leído para evitar reprocesar el backlog en bucle.
16. Sacar Cotizaciones y Compras de `soloDesarrollo` en el menú — solo
    después de cerrar al menos la Prioridad 1.

## Decisión de alcance (2026-10-06): solo Cotizaciones

- **Compras / órdenes de compra: en pausa.** No se construye nada de Compras por
  ahora (ni impuestos por línea, ni Compra→Inventario, ni bitácora, ni adjuntos,
  ni centro de costos). Se mantiene el código existente tal cual, oculto en el
  menú como hasta hoy.
- **Numeración de cotización por ciudad**: el único cambio entre sedes es el
  consecutivo y su prefijo:
  - `CTG` Cartagena
  - `BAQ` Barranquilla
  - `SMR` Santa Marta
  - `MTR` Montería
  - `RCH` Riohacha
  Formato de referencia: `BAQ-0446`. Por lo tanto cada cotización necesita una
  **ciudad/sede** y un consecutivo por sede.

### Lista priorizada — solo Cotizaciones

**Prioridad 1 — Bloquea el uso real**
1. **Sede (ciudad) y numeración por prefijo** (`CTG/BAQ/SMR/MTR/RCH` +
   consecutivo por sede). Hoy la numeración es global (`COT-0001`).
2. **IVA 19% a nivel de documento** y **descuento % de cabecera** (en el
   formato real, IVA y descuento van en el resumen, no por línea). Totales en
   cascada: Gran total → Descuento → Subtotal → IVA → Valor total.
3. **Rechazo manual** (`cotizada → rechazada` sin depender del correo).
4. **Editar y reenviar una cotización ya enviada** (volver a `en_revision` desde
   `cotizada` conservando el historial del hilo).

**Prioridad 2 — Documento igual al del taller**
5. Encabezado: NIT, contacto, ciudad, fecha, **T. Pago** (`CREDITO`/contado),
   descuento %.
6. Por línea: **Línea**, **Equipo**, **Código** y **Descripción** como **texto
   libre por ítem**, no catálogo. Confirmado por el taller (2026-10-06): la
   Línea, el Equipo y el Código los define el cliente según cada solicitud de
   servicio, no están estandarizados. Además: **Unidad** y **Fecha de entrega**
   por ítem. Consecuencia: no se crea un catálogo de líneas/equipos/códigos desde
   la cotización, y el campo `equipo_id` de la cabecera no aplica.
   **Regla confirmada (2026-10-06)**: el equipo se guarda en el registro
   `equipos` **solo cuando queda relacionado con una OT**, como funciona hoy
   (alta del equipo al crear la OT). Lo escrito en la cotización no genera
   registro de equipo por sí solo.

**Prioridad 3 — Robustez**
7. Permisos finos (ver/enviar/rechazar separados).
8. Condiciones/garantía en el PDF (texto configurable) — confirmar con usuario.

**Prioridad 4 — Correo (solo para el canal automático)**
9. Configurar y probar IMAP/SMTP reales.
10. Arreglar el no-op de `marcarProcesado()`.
11. Sacar Cotizaciones de `soloDesarrollo` — después de cerrar la Prioridad 1.

**Descartado por ahora**: vigencia (no aparece en el formato real; confirmar),
comparación de proveedores, todo lo de Compras.

## Ajustes según formatos reales del taller (2026-10-06)

El usuario compartió un ejemplo real de **orden de compra** (`OC-1-231`, emitida
a REVOPINTURA S.A.S., elaborada en **Siigo**) y de **cotización** a cliente
(`BAQ-0446`, a LASA SAS, sede Medellín/Barranquilla). Hallazgos:

**Orden de compra (real vs. nuestro `Compra`)**
- Impuestos **por línea**: IVA 19% y **retención en la fuente** (0% en el
  ejemplo), más un **descuento por línea** (`Valor desc.`). Nosotros solo tenemos
  total plano.
- Totales en cascada: Total bruto → Descuentos → Subtotal → IVA 19% → Total a
  pagar. Nosotros solo `total`.
- **Centro de costos** (campo obligatorio en su formato). No existe en el modelo.
- **Referencia a la cotización del proveedor** (`Observaciones: COTIZACION No.
  C-1-2874`). Nosotros no guardamos el número de cotización del proveedor.
- **Anexos** (adjuntar archivo) — confirma la Prioridad 2 item 6.
- Datos del comprador (NIT, dirección, teléfono) y del proveedor (NIT, contacto,
  correo) en el encabezado.
- Numeración: el taller usa `OC-<sede>-<consecutivo>`; nosotros `COM-0001`.
- **Ojo con la convención de precios**: en el ejemplo, `Vr. Unitario` es
  **antes de IVA** pero `Vr. Total` de cada línea **ya incluye IVA**, mientras
  `Total bruto` es antes de IVA. Hay que decidir una convención explícita y
  aplicarla igual en PDF, totales y costeo.

**Cotización (real vs. nuestra `Cotizacion`)**
- Encabezado con **NIT, cliente, contacto, fecha, ciudad, teléfono, dirección,
  email, descuento % y T. Pago** (`CREDITO`/contado). Nosotros: cliente y total.
- Por línea: **Línea** (referencia al ticket de servicio, p. ej.
  `ST-BAQ-3092026-172753-1`), **Equipo** (código del equipo, p. ej. `LAA 662`),
  **Código** de producto, **Unidad**, y **Fecha de entrega** por ítem.
  - Esto justifica por fin el campo `equipo_id`, pero a nivel de **línea**, no
    solo de cotización.
- Totales: Gran total → Descuento → Subtotal → IVA 19% → Valor total.
- Numeración `BAQ-0446` = prefijo por **sede** + consecutivo. Por eso `sede`
  aparece en `solicitudes_despacho` y debe existir también en cotizaciones y
  compras.
- **No aparece vigencia** ("válida hasta") en el formato real. Antes lo
  pusimos como P2; ahora lo bajamos a **a confirmar** con el usuario (puede que
  no lo usen).

**Dato importante**: el documento de compra está **elaborado por Siigo**. Antes
de construir la numeración y los totales, hay que confirmar si:
(a) Siigo seguirá siendo el sistema contable y nosotros solo gestionamos la
operación (entonces hay que **exportar/integrar** hacia Siigo), o
(b) nosotros reemplazamos a Siigo para estos documentos (entonces replicamos su
formato completo). Son trabajos muy distintos.

### Lista priorizada revisada

**Prioridad 1 — Bloquea el uso real (ajustada)**
1. Botón "Rechazar" manual en Cotizaciones (sin cambios).
2. Compra → Inventario: `facturar()` genera entrada real (sin cambios).
3. Editar/reenviar cotización ya enviada (sin cambios).
4. **Modelo de impuestos y descuentos por línea** (IVA 19%, retención %,
   descuento) con la convención de precios definida, en cotización **y** compra.
   Sube de "IVA en la cotización" a esto porque el formato real lo exige en
   ambos documentos.
5. **Sede** en cotización y compra, y numeración con prefijo de sede.

**Prioridad 2 — Documento serio (ajustada)**
6. Campos de encabezado completos (NIT, contacto, ciudad, T. Pago, descuento %)
   en ambos PDFs.
7. Por línea en cotización: **Línea/ticket**, **Equipo**, **Código**, **Unidad**,
   **Fecha de entrega**.
8. Compra: **centro de costos**, **número de cotización del proveedor**, y
   **adjuntos** (Anexos).
9. Bitácora de Compras (`mensajes_compra`).
10. Condiciones/garantía en el PDF (texto configurable) — confirmar con usuario.

**A confirmar con el usuario antes de construir**
- Vigencia de la cotización (no aparece en su formato).
- Siigo: integrar/exportar vs. reemplazar.
- Comparar cotizaciones de varios proveedores (P3 item 13) — no aparece en el
  ejemplo de OC, probablemente no hace falta todavía.

**Prioridad 3 — Robustez** (sin cambios: campo `equipo_id` pasa a P2 por la
línea; permisos finos; costo anclado al catálogo; comparar proveedores).

**Prioridad 4 — Correo** (sin cambios).

## Próximo paso
Ver la sección de decisión de alcance de arriba: el trabajo activo es solo
Cotizaciones. Pendiente de confirmar con el usuario: vigencia, y qué
representan los códigos de línea tipo `ST-BAQ-…` y `LAA 662` (¿OT propia o
ticket externo? ¿código de equipo interno?).

**Para plantear al cliente** (2026-10-06): cuando una cotización es aceptada,
¿debe el sistema crear la OT prellenada con los ítems y equipos de la
cotización? Hoy no existe ese paso; no se construye hasta tener respuesta.
