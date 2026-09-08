# Analytics Architecture

## Eventos versionados

Ejemplos:

- `viewer.joined.v1`
- `viewer.left.v1`
- `player.heartbeat.v1`
- `poll.answered.v1`
- `cta.clicked.v1`
- `resource.downloaded.v1`
- `scene.changed.v1`
- `stream.health.v1`

## Envelope

Incluir cuando corresponda:

- schema_version
- occurred_at
- tenant_id
- workspace_id
- event_id
- session_id
- actor_id / attendee_id
- correlation_id
- metadata

## Métricas

- registrations;
- attendance;
- peak concurrency;
- watch time;
- engagement;
- questions;
- polls;
- CTA;
- conversions;
- revenue;
- attribution;
- sponsor performance;
- stream health.

## Heartbeats

Diseñar batching/agregación.

No almacenar un volumen innecesario sin propósito analítico.
