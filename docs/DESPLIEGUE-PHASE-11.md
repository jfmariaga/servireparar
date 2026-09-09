# Despliegue de Phase 11 (endurecimiento de OT)

## Qué había que hacer y por qué

Las **migraciones** cambian la *estructura* de la base (tablas y columnas nuevas) y se aplican con
`php artisan migrate`. Pero dos cosas de Phase 11 no son estructura, son **datos de catálogo**, y esos
normalmente se cargan con *seeders* — que **no** corren en `migrate`:

| Dato nuevo | Dónde vivía | Riesgo si no se carga |
|---|---|---|
| Estado `cancelada` en `estados_ot` | fila de catálogo | no se podría cancelar una OT / tarea |
| Permiso `attend-ot-insumo` (+ asignación a Almacenista y Administrador) | fila de permiso + tabla `role_has_permissions` | el Almacenista quedaría con **403** en `/inventario/insumos-ot` |

Además, **spatie/permission cachea** el mapa de roles/permisos (TTL 24 h). Tras tocar permisos hay que
limpiar ese caché o la app no "ve" el permiso nuevo hasta que expire (`php artisan permission:cache-reset`).

### Resuelto: ahora basta `php artisan migrate`

Se añadieron **migraciones de datos idempotentes** para no depender de re-correr seeders:

- `2026_09_08_130001_add_cancelada_estado_and_finalizacion_pendiente.php` → inserta el estado
  `cancelada` (`updateOrInsert`).
- `2026_09_08_150001_ensure_attend_ot_insumo_permission.php` → crea el permiso `attend-ot-insumo`, lo
  asigna a Almacenista + Administrador y hace `forgetCachedPermissions()`.

Los seeders (`EstadosOtSeeder`, `RolesSeeder`) **también** quedaron actualizados, para que un
`migrate:fresh --seed` en local siga funcionando. No hay que correrlos a mano.

---

## Pasos de despliegue

### Entorno local / desde cero
```bash
php artisan migrate:fresh --seed
npm run build
```

### Entorno con datos reales (staging / producción)
```bash
git pull                    # trae main con Phase 11
composer install --no-dev --optimize-autoloader   # si cambió composer.lock (no cambió en Phase 11)
php artisan down             # opcional, ventana de mantenimiento
php artisan migrate --force  # aplica TODAS las migraciones nuevas, incluidas las de datos
npm ci && npm run build      # o subir el build ya compilado
php artisan optimize:clear   # limpia config/route/view/event cache
php artisan up
```

`php artisan migrate --force` ya deja:
- todas las tablas/columnas nuevas (`detalle_ot_insumos`, `ot_herramientas`, `notifications`,
  `solicitudes_insumo_ot.detalle_ot_insumo_id`, `ordenes_trabajo.alertado_vencimiento_en`,
  `detalle_ot.finalizacion_solicitada_en`, etc.),
- el estado `cancelada`,
- el permiso `attend-ot-insumo` asignado y el caché de permisos limpio.

**No hace falta** `db:seed --class=RolesSeeder` ni `permission:cache-reset` a mano. (Si aun así quieres
forzarlo, ninguno de los dos hace daño: son idempotentes.)

### Scheduler (necesario para las alertas de vencimiento)
En el servidor, una entrada de cron:
```
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```
Corre `ot:revisar-vencimientos` a diario (avisa una sola vez por OT; `--reenviar` para forzar).

### Correo al cliente (FR-011)
`config/mail.php` usa `MAIL_MAILER` del `.env`. En local está en `log` (el correo queda en
`storage/logs/laravel.log`). En producción configurar SMTP real y `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME`.

---

## Verificación post-despliegue (humo)
1. Login como Almacenista → `/inventario/insumos-ot` abre (no 403).
2. Login como Jefe → crear OT con un insumo → aparece "Planificar OT" y el checklist de cierre por
   defecto (5 ítems).
3. La campana (arriba a la derecha) muestra el conteo de no leídas.
4. `php artisan ot:revisar-vencimientos` corre sin error.
