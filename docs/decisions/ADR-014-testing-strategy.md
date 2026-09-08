# ADR-014 — Estrategia de testing (Pest) y análisis estático

## Status

Accepted (2026-09-08)

## Decision

- Backend: **Pest** sobre PHPUnit 11. **SQLite in-memory** para la suite (rápida, sin red). Capas: Unit (Value Objects), Feature (auth, tenancy, workspaces, feature flags, entitlements), Policy y **cross-tenant isolation (obligatorio)**.
- Los datos base (planes + roles/permisos) se siembran en `Tests\TestCase::setUp` tras `RefreshDatabase`.
- Frontend: **Vitest** + Vue Test Utils (jsdom). Playwright queda preparado para E2E en fases posteriores.
- Static analysis backend: **Laravel Pint** (estilo, `declare(strict_types=1)`) + **Larastan/PHPStan nivel 6**.
- Static analysis frontend: **ESLint** (flat config, Vue + TS) + **TypeScript strict** + **Prettier**.

## Consequences

- Los proveedores se testean con fakes; la suite normal no requiere Internet.
- El pipeline de CI ejecuta ambos conjuntos (ver `.github/workflows/ci.yml`).
