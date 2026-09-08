# ADR-004 — Estrategia de IDs

## Status

Accepted (2026-09-08)

## Context

Como SaaS multi-tenant no debemos exponer identificadores secuenciales: habilitarían enumeración de recursos e IDOR. A la vez, los `joins`/foreign keys internos deben ser compactos y eficientes en InnoDB, y todos los índices secundarios cargan la primary key.

Se evaluaron dos opciones: (A) ULID como PK `CHAR(26)`, y (B) BIGINT interno + ULID público.

## Decision

Cada entidad del control-plane usa **ambos**:

- `id` `BIGINT UNSIGNED AUTO_INCREMENT` como **primary key interna** — nunca expuesta.
- `ulid` `CHAR(26)` `UNIQUE` como **identificador público** — usado en route-model-binding y en la API.

Las foreign keys referencian el `id` BIGINT (8 bytes, localidad de inserción óptima). Las API Resources exponen el ULID como `id`; el BIGINT se oculta de la serialización (`$hidden`) como defensa en profundidad. Implementado en el trait `App\Domain\Shared\Concerns\HasPublicId` (genera el ULID en `creating`, fija `getRouteKeyName()` al ULID y oculta la PK).

## Consequences

- Índices secundarios compactos; joins internos por entero.
- La integración con Spatie (teams y morph keys `unsignedBigInteger`) funciona sin adaptar migraciones.
- Disciplina requerida: jamás exponer `id`. Garantizado por el trait (route-binding + `$hidden`) y por las Resources.
- Coste: doble columna e índice único adicional por tabla; el ULID se genera en la aplicación (creación distribuida OK), es time-ordered y ordenable.
- Tablas append-only de muy alto volumen (p. ej. `audit_logs`) ya obtienen el beneficio de PK BIGINT monotónica de forma uniforme.
