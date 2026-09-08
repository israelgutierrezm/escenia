# Scalability Strategy

## Objetivo

Evolucionar desde pocos tenants hasta miles de tenants y eventos masivos sin reescribir el core.

## Horizontal scaling

Escalar independientemente:

- Laravel API nodes;
- Reverb nodes;
- queue workers;
- media/SFU;
- ingress workers;
- egress workers;
- analytics collectors.

## Video

El ancho de banda masivo se entrega por CDN, no por Laravel.

## Analytics

No enviar millones de heartbeats a modelos Eloquent OLTP.

Diseñar:

Client -> Collector -> Transport -> Consumers -> Analytics Store.

Redis/queues pueden cubrir etapas iniciales.

Kafka/Redpanda solo cuando exista justificación.

## DB

- indexes por patrones reales;
- EXPLAIN en queries críticas;
- cursor pagination cuando corresponda;
- zero-downtime migrations;
- async backfills.

## Resilience

Aplicar según criticidad:

- idempotency;
- distributed locks;
- retries + exponential backoff + jitter;
- circuit breakers;
- outbox pattern;
- dead-letter states.

## Multi-region

No implementar inicialmente.

Guardar metadata suficiente para futuro:

- preferred media region;
- storage region;
- tenant residency.
