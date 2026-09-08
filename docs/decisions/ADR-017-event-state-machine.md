# ADR-017 — Máquina de estados del ciclo de vida del evento

## Status

Accepted (2026-09-08)

## Decision

- Estados (`EventStatus`): `draft → scheduled → live → ended → archived`, más `canceled` (desde `draft`/`scheduled`). `archived` y `canceled` son terminales.
- Las transiciones permitidas se declaran en el propio enum (`allowedTransitions()` / `canTransitionTo()`); no se permite ninguna escritura directa de estado.
- `TransitionEventAction` aplica la transición: valida su legalidad (si no, lanza `InvalidEventTransitionException` → `422 invalid_transition`), sella `actual_start_at`/`actual_end_at`, emite el domain event `EventStatusChanged` y registra auditoría.
- La API expone `allowed_transitions` en `EventResource` para que el frontend ofrezca sólo las acciones válidas.

## Consequences

- Las transiciones inválidas son imposibles por diseño, auditables y observables.
- Fases posteriores (broadcast, notifications, analytics) reaccionan a `EventStatusChanged` sin acoplarse a la lógica de transición.
