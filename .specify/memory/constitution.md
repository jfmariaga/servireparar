# SERVIOPS (SERVIREPARAR S.A.S) Constitution
<!-- Sistema web de gestión operativa: OT, inventario, personal, equipos/mantenimiento, compras, KPIs y notificaciones -->

## Core Principles

### I. Stack Tecnológico Fijo
El proyecto se construye sobre PHP 8.2, Laravel 11 (^11.31), Livewire 3.6.4 + Volt 1.7, Laravel Sanctum 4.0
y spatie/laravel-permission 6.9, tal como está definido en `composer.json`. No se introducen frameworks o
paquetes alternativos para resolver lo que este stack ya cubre (autenticación, autorización basada en
roles/permisos, reactividad de UI). La interfaz es Livewire/Volt full-stack; no se construye una SPA
desacoplada de una API salvo que un spec futuro lo justifique explícitamente. Sanctum se usa para sesiones
autenticadas de la SPA/Livewire y, si aplica, para tokens de integraciones externas (ej. webhooks de correo).

### II. Roles y Autorización basados en spatie/laravel-permission
Los roles del sistema — Administrador, Jefe de Taller, Almacenista, Técnico — y sus permisos se gestionan
exclusivamente con spatie/laravel-permission, reflejando las tablas `ROLES` y `USUARIO_ROL` del modelado ER.
Toda regla de acceso (qué rol ve o ejecuta qué acción) se implementa como permiso/policy, no como lógica
condicional ad-hoc dispersa en las vistas o controladores.

### III. Alcance Cerrado por Contrato
Los 7 módulos funcionales de la cotización del 30/06/2026 (OT, Inventario, Personal, Equipos y
Mantenimiento, Solicitudes de Compra, Reportes/KPIs, Notificaciones) son el límite del alcance contratado.
Cualquier funcionalidad no descrita allí que surja durante `/speckit-clarify` o el desarrollo debe marcarse
como fuera de alcance o pendiente de aprobación comercial — no se implementa por defecto ("Cambios
posteriores a aprobación funcional podrán generar costos adicionales").

### IV. Trazabilidad y Auditoría
Toda operación sobre Órdenes de Trabajo, Inventario, Compras y Cotizaciones debe quedar auditable: quién
ejecutó la acción, cuándo, y qué cambió. Esto es coherente con las tablas de historial y evidencia del ER
(`EVIDENCIAS_OT`, `CHECKLIST_OT`, `MOVIMIENTOS_INVENTARIO`) y con el requisito contractual de trazabilidad
incluso cuando un Administrador o Jefe de Taller corrige una OT ya iniciada.

### V. Entrega Modular por Fases
El desarrollo sigue el cronograma de 4 fases (Inicio/Configuración → Módulos Operativos Centrales →
Módulos Complementarios e Integraciones → Pruebas/Capacitación/Entrega), asociado a los 3 hitos de pago
(40% / 30% / 30%). Cada spec de módulo debe poder demostrarse de forma independiente al finalizar su fase,
sin depender de módulos de fases posteriores para ser funcional y verificable.

## Restricciones Técnicas Adicionales

- **Base de datos**: el modelo relacional debe respetar las entidades y relaciones del diagrama ER
  (`ER.png`) como fuente de verdad inicial del dominio; cualquier desviación se documenta en el `plan.md`
  del módulo correspondiente.
- **Notificaciones**: las notificaciones internas y las notificaciones automáticas al cliente por correo se
  implementan sobre el sistema de notificaciones/colas de Laravel (queued notifications/jobs), no de forma
  síncrona bloqueante.
- **Integración de correo** (Solicitudes de Compra y Cotizaciones): recepción vía IMAP polling (job
  programado) y envío vía SMTP estándar de Laravel, sobre una interfaz de "proveedor de correo" desacoplada
  del protocolo — decisión tomada en `/speckit-clarify` del spec 006 (2026-08-24).
- **Infraestructura de despliegue**: Hostinger (VPS/hosting cloud), con dominio propio, SSL y backups
  diarios, según lo cotizado — fuera del alcance de código de la aplicación pero condiciona decisiones de
  stack (ej. evitar dependencias que requieran infraestructura no disponible en ese proveedor).

## Flujo de Trabajo Spec-Driven

Todo módulo pasa por: `/speckit-specify` (spec funcional, sin detalles de implementación) →
`/speckit-clarify` (resolver ambigüedades marcadas `[NEEDS CLARIFICATION]`) → `/speckit-plan` (diseño
técnico: modelos, migraciones, componentes Livewire/Volt, policies) → `/speckit-tasks` (tareas ejecutables
ordenadas) → `/speckit-implement`. Ningún módulo pasa a `/speckit-plan` sin que sus `[NEEDS CLARIFICATION]`
críticos hayan sido resueltos con el cliente.

## Governance

Esta constitución prevalece sobre decisiones de diseño individuales de cada spec. Cualquier spec o plan que
contradiga un principio aquí definido debe justificar la excepción explícitamente en su propio documento
(sección de "Complexity Tracking" o equivalente) o modificar primero esta constitución mediante una nueva
versión. Las enmiendas deben documentar qué cambió y por qué, y se reflejan en el número de versión.

**Version**: 1.0.0 | **Ratified**: 2026-08-24 | **Last Amended**: 2026-08-24
