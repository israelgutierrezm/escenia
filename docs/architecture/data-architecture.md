# Data Architecture

## MySQL

Fuente de verdad transaccional.

Contiene:

- identity;
- tenancy;
- events;
- configuration;
- registrations;
- commerce;
- billing;
- automation definitions;
- metadata.

## Redis

- cache;
- queues;
- distributed locks;
- rate limiting;
- presence;
- transient production state.

## ClickHouse

Introducir cuando el volumen de telemetry/analytics lo justifique.

Casos:

- viewer heartbeats;
- watch-time;
- engagement;
- player events;
- stream health;
- large event analytics.

Los dominios no deben depender directamente de ClickHouse.

## Object Storage

Guardar:

- recordings;
- tracks;
- images;
- videos;
- audio;
- transcripts;
- exports;
- captions.

Preferir uploads directos desde frontend.

## Vector Store

Qdrant únicamente cuando se implemente:

- semantic replay;
- RAG;
- content similarity;
- transcript search.

## Immutable data

Append-only cuando corresponda:

- usage ledger;
- audit;
- raw analytics;
- payment ledger;
- webhook delivery attempts.
