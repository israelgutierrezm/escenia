# ADR-016 — Agregado Event y composición por capacidades

## Status

Accepted (2026-09-08)

## Context

El producto soporta muchos "tipos" de evento (webinar, live studio, training, conference, product launch...). Modelarlos como árboles de tablas por tipo sería inmantenible; la especificación exige **composición por capacidades** (`master-specification.md`).

## Decision

- El agregado `Event` es la fuente de verdad del evento y su ciclo de vida.
- El `type` es un preset/etiqueta; el comportamiento se **compone por capacidades** (`event_capabilities`): registration, chat, qa, polls, recording, etc. Nunca ramificar por `type`.
- Las capacidades se activan por evento y se **gating por entitlements** del plan del tenant (ADR-013): activar una capacidad que el plan no incluye devuelve `403 capability_not_entitled` (`SetCapabilityAction` + `EntitlementResolver`).
- Los `event_templates` (de sistema o del tenant) siembran las capacidades por defecto al crear el evento, filtrando las que el plan no permite.
- Sub-entidades del core: `event_sessions`, `event_speakers`, `event_schedule_items`, todas tenant-scoped.

## Consequences

- Añadir un tipo de evento = nuevo preset/template, no nuevas tablas.
- Las features premium se gestionan como datos: las `features` de un plan coinciden con las capability keys.
- Implementado en `app/Domain/Events` y `app/Application/Events`.
