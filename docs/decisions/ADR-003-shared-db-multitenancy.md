# ADR-003 — Shared DB Multi-tenancy

## Status
Accepted

## Decision
Usar shared MySQL database con ownership por `tenant_id`.

## Consequences
Se requieren scopes/policies/tests cross-tenant.

Dedicated DB queda como estrategia Enterprise futura.
