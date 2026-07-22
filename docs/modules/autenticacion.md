# Módulo Autenticación

## Propósito

Registro de nuevos usuarios, inicio y cierre de sesión. Es la puerta de entrada a los perfiles con rol (`producer`, `consumer`) y determina a qué panel se redirige a cada usuario tras autenticarse.

## Rutas

| Método | URL | Handler |
|---|---|---|
| GET | `/registro` | `AuthController@showRegister` |
| POST | `/registro` | `AuthController@register` |
| GET | `/login` | `AuthController@showLogin` |
| POST | `/login` | `AuthController@login` |
| POST | `/logout` | `AuthController@logout` |

Definidas en `routes/web.php`.

## Archivos involucrados

- `src/Controllers/AuthController.php`
- `src/Core/Auth.php` (lógica de registro, login, logout, sesión)
- `src/Core/Controller.php` (`requireAuth()`, `requireRole()`, usados por otros módulos protegidos)
- `src/Core/helpers.php` (`csrf_token()`, `csrf_field()`)
- `src/Models/User.php` (constantes de rol, `publicRoles()`)
- `src/Repositories/UserRepository.php`
- `src/Views/auth/register.php`, `src/Views/auth/login.php`
- `src/Views/layouts/main.php` (formulario de logout embebido en el header)

## Datos / colecciones

Colección `users`, con documentos de forma:

```
[
  'id'            => string,
  'name'          => string,
  'email'         => string (único, en minúsculas),
  'password_hash' => string (bcrypt),
  'role'          => 'consumer' | 'producer' | 'admin',
  'created_at'    => string ISO-8601,
]
```

## Reglas de negocio / validaciones (`Auth::register()`)

| Campo | Regla |
|---|---|
| `name` | Obligatorio (no vacío tras `trim`). |
| `email` | Debe ser un correo válido (`FILTER_VALIDATE_EMAIL`) y no debe existir ya en `users` (`UserRepository::findByEmail`). |
| `password` | Mínimo 6 caracteres. |
| `role` | Solo se acepta `consumer` o `producer` desde el registro público (`User::publicRoles()`). El rol `admin` **no** se puede crear desde el formulario público; se asigna manualmente (ver `scripts/seed.php`). |

Si hay errores, `register()` devuelve `['ok' => false, 'errors' => [...], 'user' => null]` y el controlador vuelve a renderizar `auth/register` con los errores y los valores `old` (nombre y correo, no la contraseña).

## Seguridad de contraseñas

- Se almacenan con `password_hash($pass, PASSWORD_BCRYPT)` (`Auth::register()`).
- Se verifican con `password_verify()` en `Auth::attempt()`.
- La contraseña **nunca** se guarda en `$_SESSION`; solo se guarda `$_SESSION['user_id']`.
- Al iniciar sesión se regenera el id de sesión (`session_regenerate_id(true)`) tanto en `login()` como en `logout()`, mitigando fijación de sesión.

## CSRF

- `csrf_token()` genera (o reutiliza) un token aleatorio de 16 bytes guardado en `$_SESSION['csrf_token']`.
- `csrf_field()` imprime un campo oculto `<input type="hidden" name="_csrf" value="...">`.
- El campo se incluye en los formularios de login, registro, logout, compra y gestión de productos/pedidos del productor.
- **Verificación server-side**: `public/index.php` valida el campo `_csrf` de **toda** petición `POST` contra `$_SESSION['csrf_token']` usando `hash_equals()` antes de despachar al router. Si el token falta o no coincide, responde `419` y no ejecuta ninguna acción. Al ser global, ninguna acción `POST` puede saltarse la comprobación.

## Flujo de login/registro exitoso

Tras un registro o login correcto, `AuthController::redirectByRole()` envía al usuario a:

| Rol | Redirección |
|---|---|
| `producer` | `/productor` |
| `admin` | `/admin` |
| `consumer` (u otro) | `/consumidor` |

## Control de acceso

- `showRegister` y `showLogin` redirigen a `/` si ya hay sesión iniciada (`$this->auth->check()`).
- El resto de módulos protegidos usan `Controller::requireAuth()` (exige sesión) y `Controller::requireRole(...$roles)` (exige sesión + rol específico, responde 403 con `errors/403` si el rol no coincide).

## Notas / mejoras futuras

- No hay límite de intentos de login (rate limiting) ni bloqueo temporal tras fallos repetidos.
- No hay verificación de correo electrónico ni flujo de recuperación de contraseña.
- El rol `admin` solo puede crearse manualmente (seed o inserción directa en `storage/data/users.json`); no hay panel de gestión de roles.
