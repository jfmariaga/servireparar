# Bitácora de despliegue en Hostinger (servirepararsas.com)

Registro de cambios hechos en producción y en el repo relacionados con el
despliegue en Hostinger, para no repetir diagnósticos ni pisar cambios entre
sesiones. Cada entrada dice **qué se tocó, por qué, y qué vigilar**.

## Cómo está armado el servidor (importante, leer antes de tocar nada)

- El proyecto completo (`app/`, `vendor/`, `storage/`, etc.) vive en
  `~/domains/servirepararsas.com/` — es el repo git clonado ahí directo, sin
  subcarpeta.
- Hostinger sirve `~/domains/servirepararsas.com/public_html/` como raíz del
  dominio. **`public_html` NO es parte del repo git** (está en `.gitignore`
  implícito por no estar trackeado) — es una carpeta físicamente separada de
  `public/`.
- `public_html/storage` es un **symlink real** a `storage/app/public`
  (creado con `php artisan storage:link`, ver entrada 2026-09-28 #3). No lo
  borres ni lo reemplaces por una copia física — eso fue justo el bug que
  causó imágenes rotas dos veces.
- `bootstrap/app.php` tiene un `usePublicPath()` que redirige `public_path()`
  a `public_html/` cuando esa carpeta existe (solo pasa en producción; en
  local con Laragon no existe `public_html/`, así que sigue usando `public/`
  normal).
- **Cualquier cambio en `resources/css` o `resources/js` (incluye clases de
  Tailwind nuevas, aunque sea solo en un `.blade.php`) requiere recompilar y
  volver a copiar el build a mano**, porque `public_html/build` es una copia
  física de `public/build`, no un symlink:
  ```
  cd ~/domains/servirepararsas.com
  npm install
  npm run build
  rm -rf public_html/build
  cp -r public/build public_html/build
  php artisan view:clear
  ```
  Si esto no se hace, el HTML ya tiene la clase nueva pero el CSS compilado
  no la incluye (Tailwind solo compila las clases que detecta en el código
  al momento del build) — el cambio "no se ve" aunque el código ya esté bien.
- El `.env` de producción se edita a mano (no está en git). Valores que
  deben estar así, no como los de desarrollo local:
  - `APP_URL=https://servirepararsas.com`
  - `APP_ENV=production`
- Flujo de deploy normal: se edita/commitea/pushea desde la máquina local →
  en el servidor, por SSH: `git pull origin main` → si el pull trajo cambios
  de `resources/css` o `resources/js`, repetir el build de arriba → limpiar
  caché (`config:clear`, `cache:clear`, `view:clear`).

## Registro de cambios

### 2026-09-26/27 — Reorganización a public_html (403 en Hostinger)
- Causa: el dominio apuntaba a la raíz del proyecto, no a `public/`. Se
  resolvió moviendo el contenido de `public/` a `public_html/` (carpeta
  fija que exige Hostinger) y agregando `usePublicPath()` en
  `bootstrap/app.php` para que Laravel supiera dónde quedó su carpeta
  pública real. Commit `2b5e860`.
- Efecto colateral detectado después: `public_html/storage` quedó como
  **copia física congelada** (el `public/storage` original ya era una
  carpeta real, no un symlink, de una configuración previa) — cualquier
  evidencia subida después de esa fecha no llegaba a `public_html/storage`.

### 2026-09-28 — Sesión de arreglos post-producción
1. **`.gitattributes` (commit `380b692`)** — cada `git pull` en el servidor
   fallaba con "local changes would be overwritten" en archivos que nadie
   había tocado a mano; el diagnóstico mostró que era ruido de fin de línea
   (CRLF/LF), no contenido real. Se agregó `.gitattributes` con
   `* text=auto eol=lf` para forzar LF en todo el repo sin importar la
   plataforma. **Vigilar:** si vuelve a pasar un conflicto de pull con "todo
   el archivo cambiado", es la misma causa — comparar con
   `git diff --stat` (insertions == deletions en todo el archivo = ruido,
   seguro descartar con `git checkout -- archivo`).
2. **Cámara sin forzar en subida de evidencias (commit `a5c0019`)** — se
   había agregado `capture="environment"` a 4 inputs de foto, lo que en
   algunos móviles abre la cámara directo sin dar opción de elegir galería.
   Se quitó para dejar el selector nativo del navegador (cámara o
   archivos). **Corregido de nuevo el 2026-09-28 tarde** — ver entrada de
   abajo, `multiple` + sin `capture` escondía la cámara en algunos
   Android/Chrome.
3. **Symlink real de `storage` (sin commit, cambio solo en servidor)** —
   se respaldó `public_html/storage` (copia vieja) como
   `storage_copia_vieja`, se corrió `php artisan storage:link` para crear
   el symlink real a `storage/app/public`. Confirmado que no se perdió
   ningún archivo (todo lo de la copia vieja ya estaba en el storage real).
4. **`APP_URL` en producción** — seguía en `http://serviops.test` (valor de
   desarrollo local) copiado al `.env` de producción por error. Se corrigió
   a mano a `https://servirepararsas.com`. **Vigilar:** esto no está en git
   (el `.env` nunca se versiona), así que si se regenera el `.env` desde
   algún `.env.example` hay que volver a ponerlo a mano.
5. **Equipo obligatorio al crear OT (commit `44d84df`)** — las variables
   técnicas de una tarea solo se muestran si la OT tiene `equipo_id`, y el
   formulario de creación permitía guardar sin equipo. Se hizo obligatorio
   el campo "Tipo de equipo" (o elegir uno de la lista) en
   `crear.blade.php`. **No afecta OT ya creadas sin equipo** — esas siguen
   sin poder mostrar variables técnicas a menos que se les asigne un equipo
   después.
6. **Varias fotos en "Registro fotográfico de entrada" (commit `30c959f`)**
   — `fotoEntrada` (un solo archivo) pasó a `fotosEntrada` (array, hasta
   10 imágenes) con miniatura y botón de quitar por cada una.
7. **Logo del login centrado en móvil (commit `a5c0019`)** — el logo
   quedaba pegado a la izquierda por ser `w-fit` sin `mx-auto`. Se agregó
   `mx-auto`. **No se vio reflejado en el servidor tras el pull** porque
   `mx-auto` es una clase de Tailwind nueva en el proyecto (no usada en
   ningún otro archivo) — el `public_html/build` de producción nunca se
   recompiló para incluirla. Ver la nota de "recompilar assets" arriba.

8. **Cámara y galería separadas en "Registro fotográfico de entrada"
   (commit `183d85a`)** — el input combinaba `accept="image/*" multiple`
   sin `capture`; en varios Android/Chrome, agregar `multiple` a un input
   de archivo hace que el selector nativo **esconda la opción de cámara**
   y solo muestre la galería. Se separó en dos controles: "Tomar foto"
   (una a la vez, `capture="environment"`, sin `multiple`) y "Subir de
   galería" (`multiple`, sin `capture`), ambos alimentando el mismo array
   `fotosEntrada` vía los hooks `updatedFotoEntradaCamara` /
   `updatedFotosEntradaGaleria`. No introdujo clases de Tailwind nuevas
   (ya estaban compiladas), así que no requería rebuild.
9. **`trustProxies` en `bootstrap/app.php` (commit pendiente de push al
   cerrar esta entrada) — causa real de "previsualización rota" y
   probablemente de futuros 401 en cualquier URL firmada** — Hostinger
   termina el HTTPS en un proxy delante de PHP; sin `trustProxies`,
   Laravel ve cada petición como `http://` aunque el navegador use
   `https://`. Las URLs firmadas (ej. `livewire/preview-file/...`, usadas
   para la previsualización de fotos antes de guardar la OT) se generan en
   `https` (por `APP_URL`) pero Laravel las valida contra el esquema que
   *cree* haber recibido (`http`) → la firma nunca coincide → 401 siempre,
   incluso con sesión activa y `APP_KEY` correcto. Se agregó
   `$middleware->trustProxies(at: '*')` en `bootstrap/app.php`. Buena
   práctica igual, pero **resultó NO ser la causa real del 401** — ver
   punto 10. Se dejó el cambio porque no hace daño y es correcto tenerlo
   en cualquier hosting detrás de proxy/CDN.
10. **CAUSA REAL del 401 persistente: "Optimización de imágenes
    inteligentes" del CDN de Hostinger (hPanel → Rendimiento → CDN →
    Administrar → servirepararsas.com → pestaña "Optimización del sitio
    web")** — Hostinger intercepta automáticamente cualquier URL cuya
    ruta termine en una extensión de imagen (`.png`, `.jpg`, etc.) y
    trata de reprocesarla, **sin importar qué devuelva realmente el
    servidor** (interceptaba incluso una ruta de diagnóstico que solo
    devolvía JSON, con el mensaje "Invalid source image"). Como la ruta
    de previsualización de Livewire es
    `/livewire/preview-file/{filename}` y `{filename}` termina en la
    extensión real del archivo subido (ej. `...xyz.png`), el CDN
    interceptaba la petición y descartaba `?expires=...&signature=...`
    antes de que llegara a Laravel — por eso la firma nunca coincidía,
    con `APP_KEY`, esquema y todo lo demás perfectamente correctos. Se
    **desactivó "Optimización de imágenes inteligentes"** en esa pantalla
    (se dejó "Compresión de imágenes WebP" activa, esa no interceptaba la
    ruta). Confirmado con un endpoint de diagnóstico temporal que
    devolvía JSON: con `.png` al final fallaba, sin extensión funcionaba.
    **Vigilar:** cualquier URL firmada futura cuya última parte termine en
    una extensión de imagen puede volver a chocar con esto si alguien
    reactiva esa opción en hPanel.
11. **Registro fotográfico de entrada: cámara no aparecía y cada foto
    nueva borraba la anterior (commit posterior a `6729827`)** — con
    `multiple` en el input, Android/Chrome esconde la cámara (mismo
    problema del punto 8, ya se había revertido a un solo input con
    `multiple` a pedido del usuario, lo cual trajo de vuelta el problema
    de cámara). Además, sin importar `multiple`, cada vez que el usuario
    volvía a usar el mismo input, el navegador **reemplaza** la selección
    anterior por la nueva (comportamiento nativo del `<input type=file>`,
    no algo que dependa de Livewire). Solución final: el input ya NO tiene
    `multiple` (para que el selector nativo ofrezca cámara+galería de
    forma confiable) y se agregó `fotoEntradaNueva` + el hook
    `updatedFotoEntradaNueva()` que **suma** cada foto elegida al array
    `fotosEntrada` en vez de reemplazarlo. Costo aceptado: ya no se pueden
    elegir varias fotos de la galería en una sola selección — hay que
    repetir el botón por cada una — a cambio de que la cámara vuelva a
    funcionar y nada se borre.

### Cómo diagnosticar "algo dejó de verse" en producción, en orden
Antes de asumir que un fix rompió otra cosa, revisar en este orden (más
rápido a más lento):
1. ¿El servidor ya tiene el último `git pull`? (`git log -1 --oneline`
   local vs servidor).
2. ¿El cambio tocó `resources/css` o `resources/js`, o agregó una clase de
   Tailwind que no se usaba antes en el proyecto? → falta
   `npm run build` + copiar a `public_html/build` (ver arriba).
3. ¿El error es 401/403 en una URL con `?expires=...&signature=...`? →
   revisar primero la "Optimización de imágenes inteligentes" del CDN de
   Hostinger si la ruta termina en extensión de imagen (punto 10); si no
   aplica, revisar `trustProxies` (punto 9) y que `APP_KEY` no haya
   cambiado.
4. ¿El error es 404 en una imagen de `/storage/...`? → revisar que
   `public_html/storage` siga siendo symlink (`ls -la public_html/ | grep
   storage`, debe empezar con `l`) y que el archivo exista en
   `storage/app/public/...`.
5. Si nada de lo anterior aplica, recién ahí buscar una regresión real en
   el código del commit más reciente.
