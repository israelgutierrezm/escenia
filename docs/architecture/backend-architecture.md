# Backend Architecture

## Estructura objetivo

```text
app/
  Domain/
  Application/
  Infrastructure/
  Http/
```

## Domain

Contiene reglas y lenguaje del negocio.

## Application

Coordina casos de uso.

Ejemplos:

- CreateEventAction
- StartBroadcastAction
- MoveParticipantToStageAction
- IssueCertificateAction

## Infrastructure

Implementa detalles:

- LiveKit
- Stripe
- Mercado Pago
- S3
- email providers
- analytics stores

## Http

Controllers, Requests, Resources y middleware.

## Eventos

Usar Domain/Application Events para desacoplar reacciones.

## Jobs

Obligatorios para:

- media processing;
- notifications;
- large exports;
- analytics aggregation;
- AI generation;
- webhook delivery;
- external sync.

## Implementación (Fase 0)

- `Domain/`: modelos, enums, value objects (`Money`), contratos y concerns (`HasPublicId`, `BelongsToTenant`), `TenantContext`.
- `Application/`: Actions (`RegisterUserAction`, `CreateWorkspaceAction`) y DTOs.
- `Infrastructure/`: implementaciones de contratos (`DatabaseAuditLogger`, `PlanEntitlementResolver`, `DatabaseFeatureFlagResolver`), `RequestContext`.
- `Http/`: Controllers delgados (`Api/V1/...`), Form Requests, Resources, Policies, Middleware.
- Contratos enlazados en `App\Providers\DomainServiceProvider`.
