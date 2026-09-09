# ADR-031 — Scale: extracción de analítica y fan-out de eventos del Outbox

## Status

Accepted (2026-09-09)

## Context

La Fase 14 cierra el roadmap con **escala**. El roadmap lista multi-region,
extracción de analytics, Kafka/Redpanda "si se justifica", autoscaling y
madurez de DR. La regla innegociable del proyecto es **no introducir
infraestructura compleja antes de que exista una necesidad real** (CLAUDE.md
§3). Levantar ClickHouse, Kafka, réplicas multi-región y orquestación de
autoscaling excede el entorno y contradiría ese principio.

Se eligen los **seams de código** que preparan la escala manteniendo defaults
network-free, en línea con el patrón del repo (contrato + fake/null por defecto
+ stub real, seleccionado por config). Así la infraestructura enchufa sin tocar
el dominio cuando se justifique.

## Decision

- **Nuevo `ScaleServiceProvider`** que enlaza tres seams por configuración, todos
  con default local:

  1. **Captura de analítica asíncrona** (`config/analytics.php` → `capture`):
     `sync` (default, `DatabaseAnalyticsCollector`) o `queue`
     (`QueuedAnalyticsCollector` → `RecordAnalyticsEventJob` → colector MySQL en
     el worker). Mismo contrato `AnalyticsCollector`, así que los callers no
     cambian. **Desacopla el request de la escritura analítica (resuelve TD-016).**
     El job vive en Infraestructura (es el brazo async del adaptador de store) y
     depende del colector concreto a propósito: usar el contrato reencolaría en
     bucle bajo modo `queue`.

  2. **Extracción a warehouse** (`config/analytics.php` → `export`):
     `AnalyticsExporter` (contrato) con `FakeAnalyticsExporter` (default,
     in-memory, testeable) y `ClickHouseAnalyticsExporter` (HTTP JSONEachRow,
     stub no testeado). `ExtractAnalyticsAction` + comando `analytics:extract`
     (agendado, `withoutOverlapping`) envían las filas no exportadas y marcan un
     **high-water mark** `exported_at` (idempotente, reanudable). **Prepara el
     push-down de agregación y la retención fuera de MySQL (resuelve
     TD-017/TD-018).**

  3. **Fan-out de eventos del Outbox** (`config/outbox.php` → `stream`):
     `EventStreamPublisher` (contrato, primitives topic/key/payload) con
     `NullEventStreamPublisher` (default no-op), `LogEventStreamPublisher` (dev) y
     `KafkaEventStreamPublisher` (Redpanda/Kafka vía REST proxy, stub). El
     `DispatchOutboxAction` publica **después** de los handlers internos y antes
     de marcar procesado; si falla, el evento reintenta (los handlers son
     idempotentes → re-ejecutar es seguro). La `ulid` del evento es la
     **partition key** (orden por agregado + dedupe en el consumidor). Es el seam
     de integración externa / multi-región.

- **Sin cambios de esquema operativo**: solo se añade `exported_at` (bookkeeping
  write-once, como `processed_at` del Outbox) a `analytics_events`. Las filas de
  telemetría siguen siendo inmutables.

## Consequences

- El sistema queda **scale-ready** sin correr infra nueva: activar cola/
  ClickHouse/Kafka es cambiar `config/{analytics,outbox}.php`, sin tocar dominio
  ni callers.
- La analítica puede dejar de bloquear el request (cola) y volcarse a un almacén
  externo (extracción), atacando la deuda de doble escritura síncrona,
  agregación en PHP y retención en MySQL.
- El Outbox pasa de bus interno a **backbone de eventos publicable**: otros
  servicios o regiones pueden consumir el stream.
- **Deuda (TD-040..043)**: ClickHouse/warehouse real y push-down de la agregación
  del `EventAnalyticsService`; Kafka/Redpanda real + consumer groups + esquema/
  contract de eventos versionado; **multi-region** (pinning por `data_region`,
  replicación y routing por región) — enforcement real de la residencia de la
  Fase 13; **autoscaling** (Horizon/worker pools, k8s HPA) y **madurez de DR**
  (backups automatizados, restore probado, RPO/RTO, runbooks). Ver
  `docs/architecture/scale-and-dr.md` y `technical-debt.md`.
