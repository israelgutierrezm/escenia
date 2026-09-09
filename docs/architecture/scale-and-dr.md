# Scale & Disaster Recovery — postura y hoja de ruta

Este documento resume la postura de escala y recuperación ante desastres del
Event Operating System al cierre de la **Fase 14 — Scale**, y qué queda
deliberadamente diferido (con su deuda técnica).

## Principio

No se introduce infraestructura compleja antes de que exista una necesidad real
(CLAUDE.md §3). La escala se prepara con **seams de código** (contratos + fakes/
null por defecto), de modo que la infraestructura real enchufe por configuración
sin tocar el dominio.

## Implementado (seams en código, ADR-031)

| Capacidad | Seam | Default (local) | Real (config) |
|-----------|------|-----------------|---------------|
| Captura de analítica desacoplada del request | `AnalyticsCollector` (`sync`/`queue`) | `sync` (MySQL) | `queue` → job → worker |
| Extracción de analítica a warehouse | `AnalyticsExporter` + `analytics:extract` + `exported_at` | `fake` (in-memory) | `clickhouse` (HTTP JSONEachRow) |
| Publicación de eventos a un stream externo | `EventStreamPublisher` en `DispatchOutboxAction` | `null` (no-op) | `log` / `kafka` (Redpanda REST) |

- **Backbone de eventos**: el Outbox (ADR-007) ya garantiza publicación
  transaccional at-least-once a handlers internos; el fan-out añade consumidores
  externos con `ulid` como partition key.
- **Idempotencia**: extracción por high-water mark (`exported_at`); handlers del
  Outbox idempotentes; consumidores del stream deben deduplicar por `ulid`.

## Diferido (deuda técnica TD-040..043)

- **Warehouse real (TD-040)**: activar `ClickHouseAnalyticsExporter`, mover el
  push-down de agregación de `EventAnalyticsService` (hoy en PHP sobre MySQL,
  TD-017) al warehouse, y aplicar retención/poda de `analytics_events` una vez
  exportadas (TD-018).
- **Streaming real (TD-041)**: Kafka/Redpanda con consumer groups, esquema de
  eventos versionado y contrato de compatibilidad; DLQ y reproceso.
- **Multi-region (TD-042)**: enforcement de la residencia declarada en la
  Fase 13 (`data_region`) — pinning de datos por región, routing por región y
  replicación; tenants dedicados sobre infra real.
- **Autoscaling y DR (TD-043)**: Horizon + worker pools y autoscaling (p. ej.
  k8s HPA) para jobs pesados (transcripción, render, indexación AI, extracción);
  backups automatizados con **restore probado**, objetivos **RPO/RTO** y runbooks
  de conmutación/recuperación.

## Runbook mínimo (estado actual)

- **Backups**: MySQL es la fuente de verdad transaccional; el stream del Outbox
  (cuando se active) actúa como log externo reproducible. Programar copias
  gestionadas del motor MySQL por entorno (pendiente de infra).
- **Colas**: en producción usar Redis + Horizon (TD-028) con supervisión; hoy
  `sync` en local/tests.
- **Degradación**: la analítica es best-effort — su indisponibilidad no debe
  tumbar el plano operativo (el modo `queue` aísla el request del store).
