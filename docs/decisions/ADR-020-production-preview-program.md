# ADR-020 — Producción: vision mixer (preview/program) y composición de escenas

## Status

Accepted (2026-09-08)

## Context

El Studio necesita un modelo de mezcla en vivo (preview → program) y escenas versionadas (ADR-006), sin acoplar el motor de producción al de broadcast (Fase 4).

## Decision

- **Escenas versionadas**: cada escena tiene `scene_versions` inmutables con `schema_version` (ADR-006). Cada guardado crea una versión nueva y `is_current` apunta a la activa. Un `SceneDefinitionMigrator` normaliza definiciones antiguas al schema actual (identity en 1.0; seam para futuras versiones). La definición es un documento JSON (layout + elementos + overlays), no un árbol de tablas por elemento.
- **Preview/Program** como estado del studio (`preview_scene_id` / `program_scene_id`, alter aditivo y zero-downtime). `SetPreviewSceneAction` (staged, off-air) y `TakeSceneAction` (on-air, emite el domain event `SceneTaken`).
- **Run of show** como rundown ordenable con refs opcionales a escena; **brand kits** a nivel workspace (tokens JSON, un default por workspace).
- Autorización con `production.view` / `production.manage` (StudioPolicy `viewProduction` / `produce`); brand kits (workspace-level) autorizan con un chequeo de permiso directo scoped al tenant.

## Consequences

- Los diseños antiguos siguen abriéndose tras evolucionar el schema de escena.
- El cambio de programa es auditable y se emitirá por Reverb (app realtime) en una fase posterior.
- Deuda: las versiones de escena son append-only sin retención (`technical-debt.md`).
