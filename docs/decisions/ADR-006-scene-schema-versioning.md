# ADR-006 — Scene Schema Versioning

## Status
Accepted

## Decision
Las Scene Definitions tendrán `schema_version`.

Cambios incompatibles requerirán migradores de schema.

## Consequences
Los diseños antiguos pueden seguir abriéndose tras evolucionar el Studio.
