# Prompt de inicio para Claude Code / Codex

Lee completamente `CLAUDE.md`, `AGENTS.md` y toda la documentación relevante dentro de `docs/` antes de modificar el repositorio.

Quiero que trabajes como Principal Software Architect y Staff Engineer del proyecto Event Operating System.

## Tu tarea en esta sesión

NO implementes todavía funcionalidades de Studio, streaming, webinars, comercio, analytics avanzado ni IA.

Trabaja exclusivamente sobre **FASE 0 — FOUNDATION**.

Primero realiza una auditoría completa del repositorio actual.

Debes identificar:

1. estructura actual;
2. versiones de Laravel, PHP, Vue, Node, TypeScript y dependencias;
3. configuración de base de datos;
4. autenticación existente;
5. arquitectura backend actual;
6. arquitectura frontend actual;
7. convenciones existentes;
8. deuda técnica;
9. riesgos;
10. incompatibilidades con la arquitectura documentada.

Después compara lo encontrado contra:

- `docs/product/master-specification.md`
- `docs/architecture/`
- `docs/decisions/`
- `docs/roadmap/master-roadmap.md`

## Antes de programar

Entrégame un diagnóstico con:

### 1. Estado actual del repositorio

### 2. Diferencias contra la arquitectura objetivo

### 3. Riesgos encontrados

### 4. Decisiones que ya pueden conservarse

### 5. Decisiones que deben corregirse

### 6. Propuesta concreta para Fase 0

Incluye:

- estructura de carpetas;
- estrategia de módulos;
- estrategia de IDs;
- multitenancy;
- users / tenants / workspaces / memberships;
- auth;
- roles y permisos;
- feature flags;
- audit;
- logging;
- testing;
- frontend foundation;
- design system foundation;
- CI/static analysis;
- migrations iniciales.

## Después

Si no existe un bloqueo crítico, implementa únicamente Fase 0 de forma incremental.

No crees tablas de futuras fases salvo que sean indispensables para Foundation.

No inventes `events`, `webinars`, `recordings`, `payments`, etc. durante esta fase si todavía no son necesarios.

## Reglas técnicas obligatorias

- Laravel debe permanecer como Modular Monolith.
- MySQL es la fuente de verdad transaccional.
- Multi-tenancy debe quedar bien resuelto desde Foundation.
- Controllers delgados.
- TypeScript strict.
- Nada de reglas críticas de negocio en Vue.
- No hardcodear planes/permisos/límites.
- No agregar microservicios.
- No agregar Kafka, Kubernetes, ClickHouse o Qdrant durante Foundation salvo documentación/configuración estrictamente no operativa.
- Las decisiones nuevas relevantes deben generar ADR.
- Toda migración debe considerar indexes, ownership y rollback/compatibilidad.
- Implementa tests cross-tenant.
- No deshabilites seguridad para facilitar desarrollo.

## Documentación

Al terminar actualiza:

- `docs/progress/current-state.md`
- `docs/progress/technical-debt.md`
- ADRs afectados
- documentos de arquitectura afectados

## Formato final de tu respuesta

### Auditoría
### Arquitectura aplicada
### Archivos creados/modificados
### Migraciones
### Tests ejecutados
### Decisiones
### Deuda técnica
### Pendientes
### Próximo paso recomendado

No avances a Fase 1 sin una instrucción explícita.
