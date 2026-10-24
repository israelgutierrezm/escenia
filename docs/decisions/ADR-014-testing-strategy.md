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
- El pipeline de CI ejecuta ambos conjuntos (ver `.github/workflows/ci.yml`). Corrección del 2026-10-23: hasta esa fecha el job de frontend fallaba en «Setup pnpm» (versión de pnpm declarada dos veces) y nunca llegó a ejecutarse; desde entonces corre y hace typecheck, lint y build de todas las apps, y, desde el 2026-10-24, los tests de todos los paquetes que los tienen (`pnpm -r --if-present test`: admin, studio, attendee, speaker y `@escenia/api-client`, este último en el entorno `node` de Vitest, sin jsdom).
- Frontend: se simula solo en los bordes — `@/lib/*` (cliente de API), `livekit-client`, `laravel-echo`/`pusher-js` y las vistas en los specs de router —; Pinia, vue-router, el ciclo de vida de Vue y `localStorage`/cookies de jsdom son reales. Sin snapshots. Los fakes de LiveKit y Echo viven en `src/__tests__/fakes/` de cada app, se comportan como la librería real (p. ej. `disconnect()` emite `Disconnected`) y el spec importa el mismo módulo que usa `vi.mock` (una sola instancia). Los specs de tiempo real fijan `VITE_REVERB_*` con `vi.stubEnv` porque `.env.local` los define en local, y el orden de los tests es fijo (sin `sequence.shuffle`) porque el router y Echo son singletons de módulo.
- Las dependencias se auditan aparte (`.github/workflows/audit.yml`) en push, PR y a diario: `composer audit` (desde el 2026-10-22) y `pnpm audit`, que incluye las devDependencies (desde el 2026-10-23).
