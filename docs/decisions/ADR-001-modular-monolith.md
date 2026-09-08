# ADR-001 — Modular Monolith

## Status
Accepted

## Context
El producto tendrá muchos dominios pero comenzar con microservicios agregaría complejidad operativa prematura.

## Decision
Usar Laravel como Modular Monolith con bounded contexts explícitos.

## Consequences
- despliegue inicial simple;
- transacciones locales sencillas;
- boundaries deben mantenerse disciplinadamente;
- servicios podrán extraerse más adelante.
