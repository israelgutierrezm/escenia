# ADR-009 — Layout del repositorio y monorepo frontend

## Status

Accepted (2026-09-08)

## Context

El backend Laravel es el control-plane. El frontend evolucionará hacia varias SPAs (`admin`, `studio`, `attendee`, `speaker`, `public`) con paquetes compartidos (`ui`, `api-client`, `types`).

## Decision

- Laravel vive en la **raíz** del repositorio (coincide con `app/Domain...` de `backend-architecture.md`).
- El frontend es un **monorepo pnpm** en `apps/*` + `packages/*` (workspace root en `package.json` + `pnpm-workspace.yaml`).
- Se elimina el frontend demo de Laravel; el backend es una **API JSON pura** bajo `/api/v1`.
- En Fase 0 sólo se construyen `apps/admin` + `packages/{types,api-client,ui}`. La estructura admite las demás sin crearlas.

## Consequences

- Backend y cada app se construyen y testean de forma independiente (CI por job).
- Los contratos de API se comparten como tipos vía `@escenia/types`; el design system arranca en `@escenia/ui` (tokens + componentes base).
