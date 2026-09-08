# ADR-011 — Autorización con scopes (Spatie teams = tenant)

## Status

Accepted (2026-09-08)

## Context

Los permisos deben tener scopes (Tenant, Workspace, Event, Session). Spatie por defecto es global.

## Decision

- Spatie Laravel Permission con **teams habilitado** y `team_foreign_key = tenant_id` → asignaciones de rol scoped por tenant.
- `tenant_memberships.role` (enum `owner`/`admin`/`member`) es la fuente de verdad de la membership; se refleja a un rol Spatie asignado en el team del tenant.
- Permisos como **catálogo de datos** (`Permission` enum + `RoleCatalog`), sembrados por `RolePermissionSeeder`. Los roles se crean globales (`tenant_id` null) y se asignan por-tenant en runtime.
- El rol de workspace vive en `workspace_memberships.role`.
- En Fase 0 sólo se implementan los scopes **Tenant** y **Workspace**; Event/Session son futuros.

## Consequences

- Sin lógica de roles hardcodeada dispersa; la autorización pasa por Policies + permisos (`$user->can(...)` dentro del team del tenant).
- Escala a scopes más finos sin reescritura.
