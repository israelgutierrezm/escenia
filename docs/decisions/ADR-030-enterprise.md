# ADR-030 — Enterprise: custom domains, API keys, SSO, residency y auditoría avanzada

## Status

Accepted (2026-09-09)

## Context

La Fase 13 lleva la plataforma a capacidades de administración **enterprise del
tenant**: dominios propios (white-label), acceso programático (Developer
Platform), inicio de sesión federado (SSO), residencia de datos y tenants
dedicados, y una consulta avanzada sobre la auditoría existente. El roadmap
lista además SAML y SCIM; construir todo el espectro (incluida la federación
real con validación de firmas y el aprovisionamiento SCIM) sería enorme y
requeriría infraestructura/credenciales que no existen en el entorno.

Se elige un **corte vertical coherente de los cinco pilares**, con los
proveedores externos detrás de contratos y *fakes* deterministas por defecto,
difiriendo con deuda explícita lo que exige infraestructura real.

## Decision

- **Nuevo bounded context `Enterprise`** (tenant-owned, ULID), en línea con el
  monolito modular. Modelos: `CustomDomain`, `ApiKey`, `SsoConnection`. Se
  **extiende `tenants`** (aditivo: `data_region`, `is_dedicated`).

- **Custom domains**: `CustomDomain` (hostname único global, estado
  `pending→active/failed`, token de challenge). La verificación de propiedad va
  detrás de `DomainVerifier` (contrato) con `FakeDomainVerifier` (default,
  network-free) y `DnsDomainVerifier` (TXT real, stub no testeado). Endpoint
  **público** `GET /domains/resolve?hostname=` que resuelve, sin scope, el
  tenant/workspace de un dominio **activo** (metadata de routing pública, jamás
  secretos). Gestión host bajo `tenant.manage`.

- **API keys (Developer Platform)**: `ApiKey` con el **patrón token-credencial**
  (raw visible una sola vez, se guarda solo el hash SHA-256, `$hidden`; prefijo
  en claro para listar y acotar la búsqueda). Middleware `AuthenticateApiKey`
  (bearer `esk_...` o `X-Api-Key`) que resuelve la key sin scope por hash y
  **establece el tenant + el team de Spatie desde la propia key** (nunca desde
  el cliente). **Scopes** como dato (`ApiKey::SCOPES`) validados al emitir y
  exigidos por endpoint. Endpoint programático de muestra
  `GET /programmatic/events` (scope `events.read`), fuera de `auth:sanctum`.

- **SSO**: `SsoConnection` (provider `oidc|saml`, **config cifrada** `encrypted:
  array` + `$hidden`, dominio de email, `default_role`, `is_active`). La
  federación va detrás de `IdentityProvider` (contrato) con
  `FakeIdentityProvider` (default, determinista) y `OidcIdentityProvider` (stub
  por HTTP, **sin validación de firma JWT** → deuda). `CompleteSsoLoginAction`
  aprovisiona *just-in-time*: enlaza el `User` por email (identidad global),
  crea la membership con el `default_role` **solo en el primer login** (nunca
  degrada un rol existente) y refleja el rol en Spatie; el controlador público
  establece la sesión Sanctum (mismas semánticas que el login por password).
  **El `default_role` se restringe a `admin|member`**: la propiedad del tenant
  (`owner`) nunca se delega a un IdP externo (guard anti escalada de privilegios).

- **Residencia + dedicado**: `data_region` (`us|eu|ap`) e `is_dedicated` en el
  tenant, leídos/editados por el Owner en `GET/PUT /enterprise/settings`.
  Registran **intención** en el control plane y quedan auditados; el *pinning*
  real de región y el aprovisionamiento de infra dedicada son futuros.

- **Auditoría avanzada**: `GET /audit-logs` sobre la tabla `audit_logs`
  existente, con filtros (action, actor, auditable_type, rango de fechas) y
  paginación. La tabla **no** está bajo el TenantScope global, por lo que el
  filtro por tenant se aplica explícitamente en el controlador. Gated por
  `audit.view`.

- **RBAC sin sprawl**: se **reutilizan** `tenant.manage` (configuración
  enterprise → solo Owner) y `audit.view` (consulta → Owner/Admin). No se
  añaden permisos nuevos; estos módulos son "administración del tenant", no
  gestión de un evento (se distinguen del `enterprise.view/manage` de la Fase 12,
  que es Enterprise *Events*). La concern `AuthorizesTenantPermission` centraliza
  el chequeo a nivel tenant (sin policy de modelo porque no hay modelo de evento
  al que colgarlo).

## Consequences

- El tenant puede operar marca propia (dominios), integrarse por API con scopes,
  federar el login con aprovisionamiento JIT y declarar su residencia, todo
  sobre los patrones ya establecidos (contratos + fakes, token-credencial,
  cifrado en reposo, auditoría, resolución server-side del tenant).
- Los proveedores externos quedan aislados: activar DNS/OIDC reales es cambiar
  `config/enterprise.php`, sin tocar el dominio.
- **Deuda** (TD-035..039): SAML/OIDC real con validación de firma/JWKS (el stub
  OIDC lee claims sin verificar la firma), **SCIM** (aprovisionamiento/
  desaprovisionamiento automático), **enforcement** de residencia (hoy solo
  intención) e infra **dedicada** real, emisión de **TLS** para los dominios y
  entrega del asset de routing en el edge, y rate-limit/telemetría por API key
  más allá de la muestra. Ver `technical-debt.md`.
