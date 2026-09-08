# Current State

## Fase actual

**Fase 0 — Foundation — COMPLETED** (2026-09-08), en `main`.

**Fase 1 — Event Core — COMPLETED** (2026-09-08), mergeada a `main` (incluye optimistic locking de transiciones).

**Fase 2 — Studio MVP — COMPLETED** (2026-09-08), en la rama `feature/studio-mvp` (pendiente de revisión/merge). No avanzar a Fase 3 — Production Engine sin instrucción explícita.

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

### Event Core (Fase 1 — `app/Domain/Events`, `app/Application/Events`)
- Agregado `Event` con **composición por capacidades** (`event_capabilities`) gated por entitlements — ADR-016.
- **Máquina de estados** guardada (`draft→scheduled→live→ended→archived`, `canceled`) con `TransitionEventAction`, domain event `EventStatusChanged` y auditoría — ADR-017.
- Sub-entidades: `event_sessions`, `event_speakers`, `event_schedule_items`; `event_templates` (sistema + tenant) con `CreateEventAction`.
- Permisos `events.*` (RBAC), `EventPolicy`, API `/api/v1/events` (CRUD + transition + capabilities + sessions/speakers/schedule + templates).
- Tablas: `events`, `event_capabilities`, `event_sessions`, `event_speakers`, `event_schedule_items`, `event_templates`.
- Contratos de frontend en sync: tipos de Event en `@escenia/types` + métodos en `@escenia/api-client`.

### Studio MVP (Fase 2 — `app/Domain/Studio`, `app/Domain/Media`, `app/Application/Studio`, `app/Infrastructure/Media`)
- **Media plane** detrás de `MediaProviderContract` + VOs agnósticos; `FakeMediaProvider` (default) y `LiveKitMediaProvider` (tokens JWT reales) por `config/media.php` — ADR-018. El dominio nunca ve el SDK.
- `studios`, `studio_sessions`, `studio_participants`, `studio_guest_links`; start/end de sesión provisiona/cierra room vía provider.
- **Ciclo de vida del participante** (invited→green_room→backstage→stage→left) con máquina de estados guardada + optimistic locking (409) — ADR-019; grants de media por rol+stage (`ParticipantGrantPolicy`).
- **Guest links** firmados (hash en DB), con expiración/single-use/revocación; canje en flujo **público** (`/studio/guest/{token}/join`) con tenant derivado del link (`TenantContext::runFor`).
- Permisos `studio.*` + `StudioPolicy`; emisión de access tokens a los participantes.
- Contratos de frontend de Studio en sync (`@escenia/types` + `@escenia/api-client`).

### Frontend (`apps/`, `packages/`)
- `apps/admin`: login + dashboard (tenants/workspaces), store Pinia de auth, guard de router, cliente tipado con CSRF de Sanctum y header `X-Tenant-Id`.
- `packages/types` (contratos de API), `packages/api-client`, `packages/ui` (design tokens + `AppButton`).

## Tests ejecutados

- **Backend**: 54 passed / 200 assertions (incluye aislamiento cross-tenant, máquinas de estado de eventos y participantes con optimistic locking, gating de capacidades, guest links y emisión de tokens con el media provider fake) — `php artisan test`.
- **Frontend**: 2 passed — `pnpm --filter @escenia/admin test`.
- **Static analysis**: PHPStan nivel 6 sin errores; Pint passed; vue-tsc + ESLint sin errores; build de producción OK.

## No implementar todavía

LiveKit productivo · Studio · Broadcast · Webinars · Commerce · Analytics avanzado · AI · Conferences · ClickHouse · Qdrant · Horizon · Reverb — salvo contratos/stubs estrictamente necesarios.

## Deuda técnica

Ver `docs/progress/technical-debt.md`.

## Última actualización

2026-09-08 — Foundation implementada y verificada.
