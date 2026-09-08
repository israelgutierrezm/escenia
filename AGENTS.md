# AGENTS.md — Reglas para agentes de programación

Este archivo aplica a Codex, Claude Code y cualquier agente que trabaje en el repositorio.

## Fuente de verdad

La fuente de verdad arquitectónica está en:

- `docs/product/master-specification.md`
- `docs/architecture/`
- `docs/decisions/`
- `docs/roadmap/master-roadmap.md`
- `docs/progress/current-state.md`

## Antes de editar

1. Inspecciona el repositorio.
2. Lee la documentación relevante.
3. Identifica el bounded context afectado.
4. Identifica riesgos multi-tenant.
5. Identifica impactos en DB/API/realtime/jobs.
6. Revisa ADRs existentes.
7. No inventes una nueva convención si ya existe una.

## Cambios de arquitectura

Toda decisión que afecte una de estas áreas requiere ADR:

- estructura de dominios;
- multitenancy;
- estrategia de IDs;
- media provider;
- analytics;
- storage;
- auth;
- permisos;
- scene schema;
- queues/event bus;
- API versioning;
- deployment;
- separación de un servicio.

## Base de datos

Cada migration debe revisar:

- foreign keys;
- indexes;
- tenant ownership;
- unique constraints;
- nullable semantics;
- lifecycle;
- PII;
- compatibilidad con zero-downtime deploy.

No usar soft deletes de forma automática.

## Seguridad

Nunca:

- deshabilites authorization para “hacer que funcione”;
- filtres tenant solo en frontend;
- guardes tokens OAuth/stream keys sin cifrar;
- devuelvas excepciones internas al cliente;
- registres secretos en logs.

## Calidad

Antes de finalizar una tarea:

- ejecutar tests relevantes;
- ejecutar lint/static analysis si está configurado;
- revisar N+1;
- actualizar documentación;
- actualizar `docs/progress/current-state.md`.

## Commits

Mantén cambios relacionados y explicables.

No mezcles refactors masivos no solicitados con una funcionalidad puntual salvo que sean imprescindibles.
