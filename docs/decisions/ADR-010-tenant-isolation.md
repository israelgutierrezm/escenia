# ADR-010 — Resolución de tenant y aislamiento

## Status

Accepted (2026-09-08)

## Context

ADR-003 fija shared-DB + `tenant_id`. Faltaba el mecanismo concreto de resolución y enforcement. El tenant nunca debe confiarse ciegamente desde el frontend.

## Decision

- `TenantContext` request-scoped (`App\Domain\Tenancy\Context`).
- Middleware `ResolveTenant`: el cliente **indica** el tenant (header `X-Tenant-Id` = ULID, o route param), pero el acceso se **autoriza verificando la membership** del usuario autenticado antes de establecer contexto (403 si no es miembro). El identificador de cliente es una pista, nunca la verdad.
- Trait `BelongsToTenant` + `TenantScope` global: filtra automáticamente por `tenant_id` y autocompleta `tenant_id` al crear. Fail-closed en HTTP (excepción si se consulta un modelo tenant-owned sin contexto).
- Middleware `RequireTenant` protege las rutas tenant-scoped.
- Establecer contexto fija además el team de Spatie (`setPermissionsTeamId`).

## Consequences

- Doble protección: aislamiento de datos (cross-tenant → 404) + autorización por permisos.
- Tests cross-tenant obligatorios (implementados en `tests/Feature/Tenancy`).
- Limitación conocida: jobs/CLI deben establecer contexto explícitamente (`TenantContext::runFor`). Registrado en `technical-debt.md`.
