# ADR-005 — Separación OLTP / Analytics

## Status
Accepted

## Decision
MySQL no será el destino final de telemetry masiva.

ClickHouse se introducirá cuando la carga lo justifique detrás de una abstracción/collector.

## Consequences
El schema de analytics debe estar versionado desde el inicio.
