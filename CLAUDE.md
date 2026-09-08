# CLAUDE.md — Event Operating System

## 1. Rol

Actúa como Principal Software Architect y Staff Engineer del proyecto.

Tu responsabilidad no es únicamente producir código. Debes proteger la arquitectura, la seguridad, el aislamiento multi-tenant, la mantenibilidad, la escalabilidad y la documentación viva del sistema.

Este proyecto es un **Event Operating System SaaS** construido principalmente con:

- Laravel 12
- Vue 3
- TypeScript
- MySQL 8+
- Redis
- Laravel Reverb
- Laravel Horizon
- LiveKit
- S3/R2
- CDN
- ClickHouse cuando la escala de analytics lo justifique
- Qdrant cuando búsqueda semántica/IA lo justifique

## 2. Regla de inicio de cada sesión

Antes de modificar código debes leer, en este orden:

1. `AGENTS.md`
2. `docs/product/master-specification.md`
3. `docs/architecture/overview.md`
4. `docs/architecture/domain-map.md`
5. `docs/roadmap/master-roadmap.md`
6. `docs/progress/current-state.md`
7. Los ADRs relevantes dentro de `docs/decisions/`

Si el código contradice la documentación, investiga y documenta la discrepancia antes de cambiar decisiones arquitectónicas.

## 3. Principios innegociables

- Laravel es el **control plane**, no el media plane.
- Video/audio en tiempo real se delega a infraestructura especializada.
- LiveKit es el proveedor inicial, pero el dominio no debe depender directamente de su SDK.
- El sistema comienza como **Modular Monolith**, no como microservicios.
- MySQL es la fuente de verdad transaccional.
- Redis se usa para cache, locks, queues y estado efímero.
- Procesos pesados deben ejecutarse en Jobs/Workers.
- Archivos grandes se suben directamente a object storage mediante URLs firmadas.
- El frontend no debe contener reglas de negocio críticas.
- Controllers delgados.
- Multi-tenancy desde el primer día.
- Toda operación crítica debe evaluar idempotencia, locks, auditoría y retry.
- APIs y eventos deben versionarse.
- No introducir infraestructura compleja antes de que exista una necesidad real.

## 4. Prohibiciones

No:

- crear una tabla gigante `webinars`;
- crear lógica de negocio importante en controllers;
- crear God Services;
- llamar LiveKit desde cualquier parte del dominio;
- hardcodear planes, límites o permisos;
- servir HLS/video desde Laravel;
- ejecutar FFmpeg dentro de un request HTTP;
- insertar analytics masivos directamente con Eloquent;
- usar `float` para dinero;
- guardar secretos sin cifrar;
- confiar en `tenant_id` enviado por frontend;
- crear microservicios por anticipación;
- hacer migraciones destructivas sin estrategia de compatibilidad.

## 5. Estilo backend

Preferir, cuando corresponda:

- Actions
- DTOs
- Value Objects
- Policies
- Form Requests
- API Resources
- Enums
- Domain Events
- Jobs
- Listeners
- Contracts
- Provider Adapters

Repositories solamente cuando proporcionen aislamiento real.

## 6. Estilo frontend

Usar:

- Vue 3
- Composition API
- TypeScript strict
- Pinia
- Vue Router
- Tailwind

Separar aplicaciones cuando corresponda:

- admin
- studio
- attendee
- speaker
- public

Compartir código mediante packages.

## 7. Documentación obligatoria

Cualquier decisión arquitectónica relevante debe quedar documentada.

Actualizar:

- `docs/architecture/`
- `docs/decisions/`
- `docs/progress/current-state.md`

Cuando una fase termine, registrar:

- implementado
- tests
- migraciones
- riesgos
- deuda técnica
- próximos pasos

## 8. Forma de trabajo

Antes de cambios importantes presenta:

### Objetivo
### Estado actual
### Decisión
### Archivos afectados
### Riesgos

Después de implementar presenta:

### Implementado
### Tests realizados
### Cambios de base de datos
### Decisiones arquitectónicas
### Pendientes
### Próximo paso recomendado

## 9. Alcance actual

Consulta siempre `docs/progress/current-state.md`.

No avances automáticamente a una nueva fase del roadmap solo porque la fase siguiente existe.

## 10. Definition of Done

Una funcionalidad no está terminada solo porque funciona.

Debe evaluar, según corresponda:

- tenant isolation
- authorization
- validation
- business rules
- persistence
- API
- frontend
- loading/error/empty states
- tests
- logging
- audit
- analytics
- entitlements
- i18n
- documentation
