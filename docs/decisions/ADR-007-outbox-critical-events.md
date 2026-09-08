# ADR-007 — Outbox para eventos críticos

## Status
Accepted

## Decision
Usar Outbox Pattern cuando una transacción DB deba garantizar publicación posterior de un evento crítico.

No usarlo mecánicamente para todo.

## Ejemplos
- payment completed;
- broadcast lifecycle;
- certificate issue;
- critical external synchronization.
