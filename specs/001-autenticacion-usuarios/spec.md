# Feature Specification: Autenticación y Gestión de Usuarios

**Feature Branch**: `001-autenticacion-usuarios`

**Created**: 2026-08-24

**Status**: Draft

**Input**: Cotización SERVIREPARAR (30/06/2026) — Fase 1 del cronograma (Inicio del Proyecto & Configuración);
Mockups Ilustraciones 1-4 (Login, Registro, Recuperación de contraseña, Perfil de usuario); ER: `USUARIOS`,
`ROLES`, `USUARIO_ROL`, `TECNICOS`.

## Clarifications

### Session 2026-08-24

- Q: El mockup muestra una pantalla de "Regístrate" pública. ¿Esta funcionalidad de auto-registro debe
  implementarse de verdad, o el alta de usuarios es siempre interna? → A: Solo alta interna por el
  Administrador; el mockup de registro no se implementa como auto-registro público.
- Q: Si un usuario tiene más de un rol asignado, ¿a qué tablero se le redirige tras iniciar sesión? → A:
  Prioridad fija por rol: Administrador > Jefe de Taller > Almacenista > Técnico.
- Q: ¿Qué tan estricta debe ser la protección contra intentos repetidos de login fallido? → A: Throttle
  estándar de Laravel (límite de intentos por minuto con backoff), sin bloqueo adicional.

### Session 2026-09-01

- Q: El canal de venta sin OT (spec 003, US6) introduce tres "vendedores" que reciben la solicitud del
  cliente y piden despacho al almacén. ¿Es un rol nuevo? → A: Sí — se agrega **Vendedor** como quinto rol
  fijo del catálogo. Prioridad de redirección post-login:
  Administrador > Jefe de Taller > Almacenista > Vendedor > Técnico. Alcance del rol: consulta del catálogo
  de inventario y gestión (crear/editar/anular) de solicitudes de despacho; NO descuenta stock, NO gestiona
  catálogos ni auditorías. Actualiza FR-005 y FR-010 (antes 4 roles).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Iniciar sesión en el sistema (Priority: P1)

Cualquier usuario registrado (Administrador, Jefe de Taller, Almacenista, Vendedor, Técnico) ingresa su
correo y contraseña para acceder al sistema y ser dirigido al tablero correspondiente a su rol.

**Why this priority**: Es la puerta de entrada obligatoria a todos los demás módulos; sin esto ningún otro
flujo es demostrable.

**Independent Test**: Con un usuario y contraseña válidos precargados, se puede iniciar sesión y verificar
que se redirige al dashboard correcto según el rol.

**Acceptance Scenarios**:

1. **Given** un usuario con estado "activo" y credenciales correctas, **When** envía el formulario de
   login, **Then** el sistema lo autentica y lo redirige al tablero de su rol.
2. **Given** un usuario con credenciales incorrectas, **When** envía el formulario, **Then** el sistema
   muestra un error genérico sin indicar si el correo existe o no.
3. **Given** un usuario con estado "inactivo", **When** intenta iniciar sesión con credenciales correctas,
   **Then** el sistema rechaza el acceso indicando que la cuenta está inactiva.

---

### User Story 2 - Recuperar contraseña olvidada (Priority: P2)

Un usuario que olvidó su contraseña solicita un enlace de restablecimiento a su correo registrado y define
una nueva contraseña.

**Why this priority**: Evita que un usuario quede bloqueado del sistema y dependa de soporte manual del
Administrador para cada caso.

**Independent Test**: Se puede solicitar el enlace, recibirlo (o simularlo en entorno de pruebas) y
completar el cambio de contraseña de forma aislada.

**Acceptance Scenarios**:

1. **Given** un correo registrado, **When** el usuario solicita "¿Olvidaste tu contraseña?", **Then** el
   sistema envía un enlace de restablecimiento válido por tiempo limitado.
2. **Given** un enlace de restablecimiento válido, **When** el usuario define una nueva contraseña que
   cumple la política mínima, **Then** la contraseña se actualiza y las sesiones previas quedan invalidadas.
3. **Given** un enlace expirado o ya usado, **When** el usuario intenta usarlo, **Then** el sistema lo
   rechaza y le permite solicitar uno nuevo.

---

### User Story 3 - Gestionar el propio perfil (Priority: P2)

Un usuario autenticado visualiza su información básica (nombre, correo, rol, estado) y puede cambiar su
contraseña desde su perfil.

**Why this priority**: Autonomía básica del usuario sin necesidad de intervención administrativa,
consistente con el mockup "Gestión de perfil de usuario" (Ilustración 4).

**Independent Test**: Un usuario autenticado accede a su perfil, edita su contraseña y verifica que el
cambio se aplica en el siguiente login.

**Acceptance Scenarios**:

1. **Given** un usuario autenticado, **When** accede a su perfil, **Then** ve nombre, correo, rol activo y
   estado de la cuenta.
2. **Given** un usuario autenticado en su perfil, **When** cambia su contraseña ingresando la actual y una
   nueva válida, **Then** el sistema confirma el cambio y lo mantiene en sesión o le pide reautenticarse.

---

### User Story 4 - Administrar usuarios y roles (Priority: P1)

El Administrador crea, edita, activa/inactiva usuarios y les asigna uno o más roles del catálogo
(Administrador, Jefe de Taller, Almacenista, Vendedor, Técnico).

**Why this priority**: Sin esta gestión no es posible dar de alta al resto del personal que usará los
demás módulos (OT, Inventario, Personal); es prerrequisito operativo de todas las fases siguientes.

**Independent Test**: El Administrador crea un usuario nuevo, le asigna rol "Técnico" y verifica que ese
usuario puede iniciar sesión y solo ve las funcionalidades permitidas a su rol.

**Acceptance Scenarios**:

1. **Given** el Administrador autenticado, **When** registra un nuevo usuario con un rol asignado,
   **Then** el usuario queda creado en estado activo y puede iniciar sesión con las credenciales generadas.
2. **Given** un usuario existente, **When** el Administrador cambia su rol o lo inactiva, **Then** el
   cambio aplica de inmediato (un usuario inactivado no puede iniciar nuevas sesiones).
3. **Given** un usuario con rol "Técnico", **When** intenta acceder a una pantalla exclusiva de
   Administrador, **Then** el sistema le niega el acceso.

### Edge Cases

- ¿Qué ocurre si el Administrador intenta inactivar o eliminar su propio usuario, o el único Administrador
  activo del sistema? El sistema debe impedir quedar sin ningún Administrador activo.
- ¿Qué ocurre si un usuario tiene más de un rol asignado (ej. Jefe de Taller y Técnico)? El sistema debe
  combinar permisos y definir un dashboard por defecto (ver FR-010).
- Intentos repetidos de login fallido: se maneja con el throttle estándar de Laravel (límite de intentos
  por minuto con backoff); no se requiere bloqueo temporal adicional ni intervención del Administrador.
- Un Técnico registrado en `USUARIOS`/`USUARIO_ROL` debe existir también en `TECNICOS` (con especialidad y
  estado "activo") para poder ser asignado a tareas de OT — ver dependencia con spec 004 (Gestión de
  Personal). Esta gestión NO tiene una pantalla propia ("Empleados"): los campos de `TECNICOS`
  (especialidad, activo) se editan desde esta misma pantalla de Usuarios cuando el rol Técnico está
  asignado (ver spec 004, FR-007).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE permitir iniciar sesión con correo y contraseña, validando que el usuario
  exista y esté activo.
- **FR-002**: El sistema DEBE permitir solicitar y completar recuperación de contraseña vía enlace enviado
  al correo registrado, con expiración del enlace.
- **FR-003**: El sistema DEBE permitir a todo usuario autenticado ver y editar su propio perfil (nombre,
  teléfono, contraseña) sin poder auto-modificar su(s) rol(es).
- **FR-004**: El sistema DEBE permitir al Administrador crear, editar, activar/inactivar usuarios.
- **FR-005**: El sistema DEBE permitir al Administrador asignar y revocar roles del catálogo fijo
  (Administrador, Jefe de Taller, Almacenista, Vendedor, Técnico) a cualquier usuario, usando
  spatie/laravel-permission como mecanismo de autorización subyacente.
- **FR-006**: El sistema DEBE impedir el acceso a pantallas/acciones no autorizadas para el rol del usuario
  autenticado (autorización a nivel de policy/permiso, no solo ocultamiento de UI).
- **FR-007**: El sistema DEBE registrar `fecha_registro` y mantener el campo `estado` de cada usuario,
  conforme a la entidad `USUARIOS` del modelo ER.
- **FR-008**: El sistema DEBE impedir que el último Administrador activo del sistema sea inactivado o
  eliminado.
- **FR-009**: Las contraseñas DEBEN almacenarse con hash seguro (estándar de Laravel) y nunca exponerse en
  texto plano en ninguna vista, log o notificación.
- **FR-010**: Cuando un usuario tiene más de un rol asignado, el sistema DEBE redirigirlo tras el login al
  tablero del rol de mayor prioridad que tenga asignado, según el orden fijo: Administrador > Jefe de
  Taller > Almacenista > Vendedor > Técnico.
- **FR-011**: El sistema NO DEBE ofrecer auto-registro público de usuarios; toda alta de usuario es
  realizada exclusivamente por el Administrador (FR-004). La pantalla "Regístrate" del mockup no se
  implementa como flujo de auto-registro real.
- **FR-012**: La pantalla de gestión de Usuarios DEBE permitir filtrar por rol (incluyendo Técnico) y por
  estado activo/inactivo, y DEBE ser el único punto de la interfaz para gestionar los datos propios de
  Técnico de spec 004 (especialidad, estado en `TECNICOS`); el sistema NO DEBE tener una pantalla separada
  de "Empleados" para este propósito.

### Key Entities

- **Usuario** (`USUARIOS`): id, nombre, correo, contraseña (hash), teléfono, estado, fecha_registro.
- **Rol** (`ROLES`): id, nombre, descripción — catálogo fijo de 5 roles (Administrador, Jefe de Taller,
  Almacenista, Vendedor, Técnico).
- **Usuario_Rol** (`USUARIO_ROL`): relación N:N entre Usuario y Rol.
- **Técnico** (`TECNICOS`): extiende a un Usuario con rol Técnico — especialidad, activo (ver spec 004).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un usuario válido completa el login y llega a su tablero en menos de 5 segundos percibidos.
- **SC-002**: El 100% de las pantallas/acciones restringidas por rol son inaccesibles (HTTP 403 o
  equivalente) para roles no autorizados, verificable por pruebas automatizadas de policies.
- **SC-003**: Un usuario puede completar el flujo de recuperación de contraseña sin intervención del
  Administrador en el 100% de los casos donde el correo registrado es válido y accesible.
- **SC-004**: El sistema nunca queda en un estado con cero Administradores activos.

## Assumptions

- El correo electrónico es único por usuario y actúa como identificador de login.
- El envío de correos (recuperación de contraseña, bienvenida) usa el mismo mecanismo de correo saliente
  que se defina para notificaciones (ver spec 008), no un proveedor distinto.
- Los 5 roles del catálogo son fijos para el alcance contratado; no se requiere un editor de roles/permisos
  dinámico por parte del Administrador (los permisos por rol se configuran en código/seed, no en UI).
- El registro de nuevos usuarios en el día a día es responsabilidad exclusiva del Administrador (FR-011).
