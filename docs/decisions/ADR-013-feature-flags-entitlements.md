# ADR-013 — Feature flags y entitlements

## Status

Accepted (2026-09-08)

## Context

Se necesita activar capacidades por scope y separar plan/features/entitlements sin construir billing ni una plataforma de experiments todavía, y sin `if ($plan === 'pro')` disperso.

## Decision

- **Feature flags**: tabla `feature_flags` + `FeatureFlagResolver` con precedencia `user > workspace > tenant > global` y rollout por porcentaje determinista. Base ligera, preparada para evolucionar.
- **Entitlements**: separación explícita features / plans / entitlements / usage. `plans` es un **catálogo de datos** (features + limits en JSON). `EntitlementResolver` responde `allows()` / `limit()` / `features()` desde el plan del tenant.
- Billing completo y `usage_ledger` quedan fuera de Fase 0 (documentados como futuros).

## Consequences

- Los dominios preguntan a contratos; no ramifican por plan ni por flag.
- Deuda menor: la unicidad de flags globales se garantiza en la capa de aplicación porque MySQL trata los NULL como distintos en un índice único. Registrado en `technical-debt.md`.
