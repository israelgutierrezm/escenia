# ADR-021 — Broadcast: egress, ciclo de vida y cifrado de stream keys

## Status

Accepted (2026-09-08)

## Context

La Fase 4 lleva el estudio a producción en vivo: egress a destinos externos (multistream), grabación y health, extendiendo la abstracción del media plane (ADR-002/008).

## Decision

- **Extensión del media provider** con `MediaEgressProvider` (interface segregation): `startEgress` / `stopEgress` / `egressHealth` con Value Objects agnósticos (`EgressSpec`, `StreamOutput`, `EgressHandle`). `FakeMediaProvider` y `LiveKitMediaProvider` implementan ambos contratos y se resuelven a la **misma instancia** (alias en `MediaServiceProvider`). Laravel nunca transporta el stream.
- **Stream keys cifradas** en reposo (`encrypted` cast en `StreamDestination`), ocultas de la serialización (`$hidden`) y jamás en logs/auditoría (CLAUDE.md / security.md). Ningún Resource las expone.
- **Ciclo de vida del broadcast** (`idle → starting → live → ended`, `failed`) con máquina de estados guardada + optimistic locking (`409 broadcast_conflict`). `StartBroadcastAction` provisiona el egress a los destinos seleccionados (multistream) + grabación opcional y pasa a `live`; `StopBroadcastAction` lo detiene best-effort tras commit. Domain events `BroadcastStarted` / `BroadcastEnded` / `BroadcastHealthChanged`.
- **ADR-007 (Outbox):** los eventos críticos de broadcast se emiten hoy como domain events; migrarán al patrón Outbox cuando existan consumidores externos (analytics/notifications).
- **Multistream** vía pivot `broadcast_destinations` con estado por destino; **grabación** como flag (los assets de grabación son Fase 9); **health** como estado + endpoint de reporte (webhook del provider).
- Autorización con `broadcast.view` / `broadcast.manage` (StudioPolicy `viewBroadcast` / `broadcast`); los destinos (workspace-level) con chequeo de permiso directo scoped al tenant.

## Consequences

- El proveedor de egress es intercambiable/combinable; el dominio no ve el SDK.
- Las stream keys nunca se exponen ni se registran.
- Deuda: el egress real de LiveKit no se prueba contra infra; los samples de health masivos irán a ClickHouse (ADR-005); el Outbox queda pendiente (`technical-debt.md`).
