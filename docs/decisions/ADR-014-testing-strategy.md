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
- El pipeline de CI ejecuta ambos conjuntos (ver `.github/workflows/ci.yml`). Corrección del 2026-10-23: hasta esa fecha el job de frontend fallaba en «Setup pnpm» (versión de pnpm declarada dos veces) y nunca llegó a ejecutarse; desde entonces corre y hace typecheck, lint y build de todas las apps, y los tests del admin.
- Las dependencias se auditan aparte (`.github/workflows/audit.yml`) en push, PR y a diario: `composer audit` (desde el 2026-10-22) y `pnpm audit`, que incluye las devDependencies (desde el 2026-10-23).
