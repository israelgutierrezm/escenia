# ADR-015 — Logging estructurado, correlación y auditoría

## Status

Accepted (2026-09-08)

## Decision

- `RequestContext` (request-scoped) genera `request_id` y `correlation_id` (propaga un `X-Correlation-Id` entrante). El middleware `AssignRequestContext` los añade al `Context` de Laravel y a los headers de respuesta.
- `ShareUserContext` añade `user_id` y `ResolveTenant` añade `tenant_id` al contexto de logs cuando se conocen.
- **Auditoría**: contrato `AuditLogger` + `DatabaseAuditLogger` escribe la tabla `audit_logs` (append-only) para acciones sensibles: autenticación, creación de tenant, cambios de membership/config. El payload de contexto **nunca** contiene secretos (se sanitizan claves sensibles).
- OpenTelemetry, Sentry y métricas Prometheus quedan documentados para fases posteriores (no operativos en Fase 0).

## Consequences

- Cada línea de log y cada registro de auditoría se correlaciona con una request/traza.
- No se registran secretos (stream keys, tokens, passwords).
