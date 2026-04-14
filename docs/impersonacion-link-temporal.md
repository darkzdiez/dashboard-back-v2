# Impersonación con link temporal — Backend

Documentos relacionados:
- [Dashboard Back V2 README](../README.md)

## Objetivo

Documentar cómo `dashboard-back-v2` genera y consume enlaces temporales de impersonación, y cómo se coordina con `dashboard-front-v2` para soportar tanto aplicaciones web con sesión como frontends desacoplados con autenticación tokenizada.

## Archivos involucrados

- `src/Controllers/UserController.php`
- `src/routes/api.php`
- `src/migrations/2026_03_02_000001_create_user_login_as_links_table.php`

## Endpoints involucrados

### Generación autenticada
- `POST /api/user/login-as-link/generate`
- Requiere permiso `login-as`.
- Recibe:
  - `target_id`
  - `expiration_minutes`
- Devuelve:
  - `message`
  - `link`
  - `expires_at`
  - `expiration_minutes`

### Consumo tokenizado
- `POST /api/user/login-as-link/exchange`
- Recibe `token`.
- Devuelve un payload tokenizado con:
  - `access_token`
  - `token_type`
  - `expires_at`
  - `user`
  - `config`

### Consumo web con sesión
- `GET /api/user/login-as-link/{token}`
- Si el token es válido:
  - inicia sesión con `auth()->login(...)`
  - registra auditoría y contador de uso
  - redirige a `url('/')`
- Si el token es inválido o venció:
  - responde una página HTML simple de error

## Distinción importante: generar el link no es lo mismo que decidir el tipo de auth

El cambio principal de esta iteración fue separar dos decisiones que antes estaban mezcladas:

1. **Qué URL absoluta devolver al frontend**.
2. **Si la respuesta de login-as directo o exchange debe ser web-session o tokenizada**.

Antes, `isTokenAuthRequest()` consideraba `expectsJson()` como señal suficiente para token auth. Eso rompía apps web con sesión porque `httpRequest` siempre envía `Accept: application/json` y `X-Requested-With: XMLHttpRequest`.

Ahora el comportamiento queda así:

- `isTokenAuthRequest()` solo devuelve `true` si hay `Bearer token` o usuario autenticado por guard `api`.
- `shouldBuildFrontendLoginAsLink()` sigue pudiendo devolver un link frontend cuando la request viene desde una UI browser/XHR, aunque la app final vaya a resolver la autenticación por sesión web.

En otras palabras:

- **Token auth** se decide de forma estricta.
- **URL de frontend** se decide de forma amplia para que la UI reciba un enlace abrible en el navegador.

## Cómo se construye la URL del link

`buildLoginAsLink()` decide entre:

- `buildFrontendLoginAsLink(...)`
- `route('user.loginAsByLink', ...)`

`buildFrontendLoginAsLink(...)` usa `resolveFrontendBaseUrl(...)`, cuyo orden de prioridad es:

1. `config('app.frontend_url')`
2. Header `Origin`
3. `config('app.url')`

Esto permite que cada proyecto configure el origen del frontend sin reimplementar lógica de URL en la UI.

## Coordinación con dashboard-front-v2

El backend asume el siguiente contrato con el paquete frontend:

- `dashboard-front-v2` expone la ruta pública `/login-as-link`.
- Si el consumer tiene un store tokenizado, el frontend llama a `POST /api/user/login-as-link/exchange`.
- Si el consumer no tiene esa capacidad y frontend/backend comparten origen, el frontend puede delegar al `GET /api/user/login-as-link/{token}` para que el backend cree la sesión web.

Este contrato evita duplicar lógica distinta por proyecto y mantiene al backend como fuente de verdad para la URL absoluta del enlace.

## Persistencia y auditoría

Tabla: `user_login_as_links`

- `token_hash`
- `actor_user_id`
- `target_user_id`
- `expiration_minutes`
- `expires_at`
- `usage_count`
- `last_used_at`
- `revoked_at`
- timestamps

Eventos de auditoría:

- `impersonate-link-created`
- `impersonate-link-used`
- `impersonate-start`
- `impersonate-end`

## Notas de mantenimiento

- No volver a usar `expectsJson()` como criterio para token auth en este flujo.
- Si una app desacoplada requiere impersonación tokenizada, debe proveer `AuthContextBuilder` y el contrato de hidratación en frontend.
- Si una app es legacy con sesión web, el fallback esperado es el endpoint GET público, no el exchange tokenizado.