# ADR-019 — Ciclo de vida del participante y guest links (Studio)

## Status

Accepted (2026-09-08)

## Decision

- **Stages** (`ParticipantStage`): `invited → green_room → backstage → stage`, con `left` como terminal (semilla de Adaptive Audience: mover participantes entre green room, backstage y stage).
- Las transiciones permitidas se declaran en el enum (`canTransitionTo`) y las aplica `MoveParticipantAction` con el **mismo patrón guardado + optimistic locking** de los eventos (ADR-017): movimiento ilegal → `422 invalid_stage_transition`; carrera perdida → `409 stage_conflict`. Emite `ParticipantStageChanged`, audita y sella `joined_at`/`left_at`.
- Los grants de media se recalculan por stage (`ParticipantGrantPolicy`): p. ej. un guest en green room publica/suscribe pero sin canal de datos hasta subir a backstage/stage.
- **Guest links**: firmados, con expiración, single-use / max-uses y revocables. Se guarda solo el hash SHA-256 del token; el token crudo se muestra una vez. El **canje es un flujo público** (`/studio/guest/{token}/join`, sin auth, rate-limited) donde el token es la credencial: el link se busca sin scope y luego el tenant se establece desde el link (`TenantContext::runFor`), nunca desde la request.

## Consequences

- Aislamiento tenant garantizado incluso en el flujo público de guest join.
- Reutiliza la máquina de estados y el locking de eventos; comportamiento consistente entre dominios.
- La media realtime la transporta LiveKit; los cambios de stage se emitirán por Reverb (app realtime) en una fase posterior.
