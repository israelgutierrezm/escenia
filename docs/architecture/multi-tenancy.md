# Multi-tenancy

## Modelo inicial

Shared database + `tenant_id`.

Jerarquía:

```text
User
  |
Tenant Membership
  |
Tenant
  |
Workspace
  |
Event
  |
Session
```

## Reglas

- Un User puede pertenecer a múltiples Tenants.
- Tenant no se deriva de un parámetro arbitrario enviado por frontend.
- Resolver tenant desde contexto autenticado, dominio, membership o route binding seguro.
- Policies y queries deben verificar ownership.
- Unique constraints deben considerar tenant cuando corresponda.

## Futuro

Preparar sin implementar todavía:

- dedicated tenant DB;
- dedicated infrastructure;
- regional tenancy;
- data residency.

## Tests obligatorios

Cada bounded context multi-tenant debe tener tests que intenten acceso cross-tenant y confirmen que se deniega.

## Implementación (Fase 0)

Ver ADR-010. Resumen:

- `App\Domain\Tenancy\Context\TenantContext` (request-scoped).
- `App\Http\Middleware\ResolveTenant` verifica la membership antes de establecer contexto (header `X-Tenant-Id` = ULID); `RequireTenant` protege rutas tenant-scoped.
- `App\Domain\Shared\Concerns\BelongsToTenant` + `App\Domain\Shared\Scopes\TenantScope` (autofill + filtro global, fail-closed en HTTP).
- Tests: `tests/Feature/Tenancy/TenantIsolationTest.php`.
