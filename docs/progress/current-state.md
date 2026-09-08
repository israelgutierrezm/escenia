# Current State

## Fase actual

**Fase 0 — Foundation — COMPLETED** (2026-09-08).
Pendiente de revisión del arquitecto antes de avanzar a Fase 1 — Event Core.

## Stack instalado

- **Backend**: Laravel 12.69.1 sobre PHP 8.3.6; MySQL 8.3 (InnoDB, `utf8mb4`); Laravel Sanctum 4.3; Spatie Laravel Permission 8.3 (teams). Redis vía `predis` (opcional en local). Horizon/Reverb aún no instalados (documentados para fases posteriores).
- **Frontend**: monorepo pnpm — Vue 3.5 + TypeScript strict + Pinia + Vue Router + Tailwind 3 + Vitest. `apps/admin` + `packages/{types,api-client,ui}`.
- **Calidad**: Laravel Pint, Larastan/PHPStan nivel 6, Pest (SQLite in-memory); ESLint (flat) + vue-tsc + Prettier.

## Implementado en Foundation

### Backend (`app/`)
- Estructura modular `Domain / Application / Infrastructure / Http`.
- Identidad pública **BIGINT interno + ULID** (`HasPublicId`) — ADR-004.
- Multi-tenancy: `TenantContext`, middleware `ResolveTenant`/`RequireTenant`, `BelongsToTenant` + `TenantScope` (fail-closed) — ADR-010.
- Auth Sanctum cookie SPA: `register` / `login` / `logout` / `me`.
- RBAC Spatie **teams = tenant**, `Permission` enum + `RoleCatalog` (datos), seeder — ADR-011.
- Feature flags (`FeatureFlagResolver`, precedencia user→workspace→tenant→global) + Entitlements (`plans` como datos, `EntitlementResolver`) — ADR-013.
- Auditoría append-only (`AuditLogger`) + logging estructurado con `request_id`/`correlation_id`/`tenant_id`/`user_id` — ADR-015.
- API `/api/v1`: error envelope consistente, rate limiting, Resources / Form Requests / Policies / Actions / DTOs / Enums / Value Object `Money` — ADR-012.
- Tablas: `users`, `tenants`, `tenant_memberships`, `workspaces`, `workspace_memberships`, `plans`, `feature_flags`, `audit_logs`, `personal_access_tokens`, tablas Spatie (con `tenant_id`).

### Frontend (`apps/`, `packages/`)
- `apps/admin`: login + dashboard (tenants/workspaces), store Pinia de auth, guard de router, cliente tipado con CSRF de Sanctum y header `X-Tenant-Id`.
- `packages/types` (contratos de API), `packages/api-client`, `packages/ui` (design tokens + `AppButton`).

## Tests ejecutados

- **Backend**: 24 passed / 58 assertions (incluye aislamiento cross-tenant) — `php artisan test`.
- **Frontend**: 2 passed — `pnpm --filter @escenia/admin test`.
- **Static analysis**: PHPStan nivel 6 sin errores; Pint passed; vue-tsc + ESLint sin errores; build de producción OK.

## No implementar todavía

LiveKit productivo · Studio · Broadcast · Webinars · Commerce · Analytics avanzado · AI · Conferences · ClickHouse · Qdrant · Horizon · Reverb — salvo contratos/stubs estrictamente necesarios.

## Deuda técnica

Ver `docs/progress/technical-debt.md`.

## Última actualización

2026-09-08 — Foundation implementada y verificada.
