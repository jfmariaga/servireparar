# Implementation Plan: Notificaciones y Alertas

**Branch**: `008-notificaciones-alertas` | **Date**: 2026-08-24 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/008-notificaciones-alertas/spec.md`

## Summary

Capa transversal de notificaciones internas (campana en UI) y correo saliente al cliente, construida sobre
el sistema nativo de notificaciones de Laravel (`Illuminate\Notifications`) con canal `database` para
internas y canal `mail` para las de cliente, procesadas de forma asíncrona vía colas. Se suscribe a eventos
disparados por los módulos de origen (002 OT, 003 Inventario, 005 Equipos, 006 Compras/Cotizaciones) en vez
de que esos módulos conozcan la lógica de notificación directamente.

## Technical Context

**Language/Version**: PHP 8.2, Laravel 11 (^11.31)

**Primary Dependencies**: `Illuminate\Notifications` (núcleo de Laravel, canal `database` + `mail`),
Laravel Queues (procesamiento asíncrono, requiere un `QUEUE_CONNECTION` real en producción — `database` o
`redis`, no `sync`), Laravel Events/Listeners para desacoplar de los módulos de origen

**Storage**: MySQL/MariaDB — tabla `notifications` nativa de Laravel (`php artisan notifications:table`),
`configuraciones` (clave/valor), y la tabla `failed_jobs` nativa para reintentos de correo fallido

**Testing**: Pest/PHPUnit — feature tests disparando cada evento de origen (`OtProximaAVencer`,
`StockBajo`, `MantenimientoPreventivoProximoAVencer`, `SolicitudPendiente`) con `Notification::fake()` y
verificando que llega al canal/rol correcto; test de deduplicación cuando un usuario tiene múltiples roles

**Target Platform**: Servidor Linux (Hostinger VPS) — requiere un worker de colas corriendo
(`php artisan queue:work`, gestionado vía Supervisor o el equivalente de Hostinger) además del proceso web

**Project Type**: Web application — monolito Laravel + Livewire/Volt + 1 worker de colas en background

**Performance Goals**: Notificación visible al usuario en < 1 minuto desde el evento origen (SC-002) — con
cola `database`/`redis` y worker activo, es holgadamente alcanzable

**Constraints**: Este módulo NUNCA redefine umbrales de negocio (vencimiento de OT, mantenimiento
preventivo) — los consume desde `CONFIGURACIONES` (compartida con spec 002/005) o desde el payload del
evento que dispara la notificación

**Scale/Scope**: Volumen de notificaciones proporcional a la operación diaria del taller (decenas/día);
sin requisitos de escala especial

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- ✅ **I. Stack Tecnológico Fijo**: usa exclusivamente el sistema de notificaciones y colas nativo de
  Laravel, sin paquete de terceros para mensajería.
- ✅ **II. Roles y Autorización**: el enrutamiento de cada notificación a "el rol responsable" se resuelve
  con consultas de spatie-permission (`Role::findByName(...)->users`), no con listas hardcodeadas.
- ✅ **III. Alcance Cerrado**: sin ampliaciones — el spec no tenía `[NEEDS CLARIFICATION]` propios.
- ✅ **IV. Trazabilidad**: notificaciones fallidas quedan en `failed_jobs` para reintento (FR-007), ningún
  evento se pierde silenciosamente.
- ✅ **V. Entrega Modular por Fases**: es el último módulo funcional de Fase 3 por diseño — depende de que
  002/005/006 ya disparen sus eventos, pero el mecanismo de notificación en sí es independiente y testeable
  con eventos sintéticos antes de que esos módulos existan.

Sin violaciones. No se requiere Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/008-notificaciones-alertas/
├── plan.md              # This file
└── spec.md              # Feature specification (ya existente)
```

### Source Code (repository root)

```text
app/
├── Models/
│   └── Configuracion.php                 # clave/valor, compartida con specs 002/005 para umbrales
├── Notifications/
│   ├── OtProximaAVencerNotification.php          # canal: database (interno, Jefe de Taller)
│   ├── StockBajoNotification.php                 # canal: database (Almacenista)
│   ├── MantenimientoPreventivoNotification.php   # canal: database (responsable de equipos)
│   ├── SolicitudPendienteNotification.php        # canal: database
│   ├── OtEntregadaClienteNotification.php        # canal: mail (cliente, spec 002)
│   └── CotizacionEnviadaClienteNotification.php  # canal: mail (cliente, spec 006 — reutiliza su envío)
├── Listeners/
│   ├── NotificarOtProximaAVencer.php      # escucha evento de spec 002
│   ├── NotificarStockBajo.php             # escucha evento de spec 003
│   ├── NotificarMantenimientoPreventivo.php  # escucha evento de spec 005
│   └── NotificarSolicitudPendiente.php    # escucha eventos de spec 003/006
├── Services/
│   └── Notificaciones/
│       └── DestinatariosPorRolService.php # resuelve destinatarios evitando duplicados multi-rol (FR-002)
└── Livewire/
    └── Notificaciones/
        └── Campana.php                    # ícono + listado + marcar leída (Volt component reutilizado en el layout)

database/
├── migrations/
│   ├── xxxx_create_notifications_table.php   # `php artisan notifications:table`
│   └── xxxx_create_configuraciones_table.php
└── seeders/
    └── ConfiguracionesSeeder.php          # valores por defecto de umbrales

routes/
└── web.php               # /notificaciones (opcional, listado completo más allá de la campana)

tests/
└── Feature/
    └── Notificaciones/
        ├── AlertasAutomaticasTest.php     # un test por tipo de evento
        ├── DeduplicacionMultiRolTest.php
        ├── NotificacionClienteTest.php
        └── ReintentoFallidoTest.php
```

**Structure Decision**: Monolito Laravel; el acoplamiento entre módulos de origen (002/003/005/006) y este
módulo se hace exclusivamente vía `Illuminate\Events` — cada módulo de origen dispara un evento de dominio
propio (ej. `OtProximaAVencer`) sin saber que spec 008 existe, y los `Listeners` de este módulo son quienes
generan la notificación. Esto respeta la dirección de dependencia (008 depende de 002/003/005/006, nunca al
revés) y hace que cada módulo de origen sea testeable sin necesitar que el sistema de notificaciones esté
implementado.

## Data Model

### Notification (tabla `notifications`, nativa de Laravel — no se modifica el esquema)

Columnas estándar: `id` (UUID), `type` (clase de notificación), `notifiable_type`/`notifiable_id` (el
`User` destinatario), `data` (JSON con mensaje y enlace al caso relacionado), `read_at` (null = no leída).

### Configuracion (`configuraciones`)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| clave | string(100) unique | Ej. `ot.umbral_vencimiento_horas`, `mail.remitente_default` |
| valor | string(255) | |
| descripcion | string nullable | |

## Verificación end-to-end

1. `php artisan queue:table && php artisan notifications:table && php artisan migrate` — con
   `QUEUE_CONNECTION=database` en `.env` para desarrollo (Redis en producción si Hostinger lo soporta).
2. Disparar manualmente (o vía `Event::fake()` en test) el evento `OtProximaAVencer` de spec 002 → verificar
   que el Jefe de Taller recibe la notificación interna y que aparece en la campana con enlace a la OT.
3. Repetir para stock bajo (spec 003), mantenimiento preventivo (spec 005) y solicitud pendiente (spec
   003/006).
4. Con un usuario que tiene rol Jefe de Taller y Técnico simultáneamente, disparar un evento que aplique a
   ambos roles → verificar que solo recibe una notificación, no duplicada (FR-002).
5. Completar una OT hasta "Entregada" (spec 002) → verificar que el cliente recibe el correo automático;
   simular fallo de envío (SMTP caído) → verificar que queda registrado para reintento sin perder el evento
   (FR-007).
6. `php artisan queue:work` corriendo, ejecutar `php artisan test --filter=Notificaciones` en verde.
