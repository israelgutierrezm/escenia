# ADR-012 — Convenciones de API y manejo de errores

## Status

Accepted (2026-09-08)

## Decision

- Prefijo `/api/v1`. Respuestas vía API Resources (envoltura `data`, paginación estándar de Laravel).
- Envelope de error consistente: `{ message, error_code, request_id, errors? }` (`App\Http\ApiExceptionMapper`, registrado en `bootstrap/app.php`). **Nunca** se devuelven stack traces ni excepciones internas en producción.
- Validación vía Form Requests; autorización vía Policies. Controllers delgados que delegan en Actions.
- Rate limiting: `throttle:api` global y `throttle:login` en las rutas de autenticación.
- Auth: Sanctum stateful cookie para SPAs first-party; CORS con credenciales explícitas (`config/cors.php`, sin `*`).
- Los headers `X-Request-Id` / `X-Correlation-Id` se exponen para trazabilidad.

## Consequences

- Contrato estable y versionable; `@escenia/types` + `@escenia/api-client` lo reflejan en el frontend.
- Cambios incompatibles ⇒ nueva versión (`/api/v2`), no ruptura silenciosa.
