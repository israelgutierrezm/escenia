# ADR-023 — Analytics: plano separado, collector versionado y reporting

## Status

Accepted (2026-09-08)

## Context

La Fase 6 añade la analítica del webinar: attendance, watch-time, engagement, **heatmap** de concurrencia y **attribution**. ADR-005 ya fijó la separación OLTP/analytics: MySQL no es el destino final de telemetría masiva y el schema de analytics debe estar **versionado desde el inicio detrás de un collector**, con ClickHouse cuando la carga lo justifique. ADR-007 reserva el Outbox para publicación crítica (analytics no lo es).

## Decision

- **Plano de analytics separado (ADR-005).** `AnalyticsCollector` (contract) + tabla **append-only** `analytics_events` + `DatabaseAnalyticsCollector` (bind en `DomainServiceProvider`). Es la **única fuente de verdad analítica**, distinta de las tablas OLTP a propósito, para poder migrar el store a ClickHouse (o a un pipeline async) **sin tocar a los callers ni al esquema operativo**. Cada evento lleva `name` (enum `AnalyticsEventName`) + `version` (int) + `occurred_at` + `event_id` + `attendee_id?` + `properties` JSON. El `version` permite evolucionar la forma del payload sin romper filas históricas ni el futuro pipeline.

- **Doble escritura deliberada.** Los choke points de Fase 5 (registro, join/heartbeat/leave, chat/pregunta/voto-pregunta/voto-poll/descarga) escriben su fila OLTP **y** registran su evento analítico (una línea por action). Es la separación OLTP/analytics que ADR-005 pide, no un accidente: el OLTP es la verdad operativa; `analytics_events` es la verdad analítica y el semillero de ClickHouse. El coste es un insert barato por acción; se moverá a async/ClickHouse cuando la carga lo justifique (technical-debt).

- **Attribution.** El registro público acepta `attribution` (utm_source/medium/campaign/term/content, referrer, landing_path), **validada y saneada** (solo claves conocidas, como strings) y guardada en las `properties` del evento `registration.completed`. Nunca en la URL (security.md). El reporte de attribution agrupa registros y asistencia por `utm_source` (fallback `direct`).

- **Reporting sobre el plano analítico.** `EventAnalyticsService` es la única superficie de consulta: `summary` (registros, asistentes únicos, tasa de asistencia, pico de concurrencia, watch-time medio, totales de engagement), `attendance` (concurrencia por bucket = **heatmap**), `engagement` (totales) y `attribution`. Cuando el store pase a ClickHouse, solo cambia este servicio. A escala MVP reconstruye las sesiones de presencia en PHP a partir del stream join/heartbeat/leave (sesión abierta sin `left` se cierra en el último heartbeat); el pico se calcula con un sweep line. Volúmenes altos empujarán esta agregación al warehouse (technical-debt).

- **Autorización.** Permiso `analytics.view` (Owner/Admin/Member) en `EventPolicy` (`viewAnalytics`); endpoints host bajo `auth:sanctum` + tenant + RBAC. El asistente nunca ve analítica agregada.

- **Sin Outbox (ADR-007).** La telemetría no requiere garantía transaccional de publicación; se escribe síncrona detrás del collector. Migrará junto con los eventos críticos (broadcast/engagement) cuando exista el pipeline de consumidores.

## Consequences

- El store de analytics es intercambiable (MySQL → ClickHouse) sin tocar callers ni reportes salvo `EventAnalyticsService`.
- El schema analítico está versionado desde el día 1; los payloads pueden evolucionar sin romper histórico.
- Attribution y funnel (registro → asistencia por fuente) disponibles desde el primer webinar.
- Deuda: doble escritura síncrona (→ async/ClickHouse), reconstrucción de sesiones en PHP (→ warehouse), retención/poda de `analytics_events`, y unificación con el Outbox. Ver `technical-debt.md` (TD-016..018).
