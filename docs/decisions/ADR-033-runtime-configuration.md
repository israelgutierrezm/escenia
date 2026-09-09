# ADR-033 — Configuración en caliente: settings de sistema y de tenant

## Status

Accepted (2026-09-09)

## Context

Se pide que **todo en el sistema sea configurable** y que **cualquier API externa
o dato necesario pueda cargarse desde el propio sistema**, no solo por variables
de entorno en despliegue. Hoy los proveedores se seleccionan leyendo
`config/*.php` (que lee `env()`) en arranque; existen integraciones cifradas por
tenant para pagos y SSO, pero lo global es solo-env y nada se edita desde la app.

## Decision

- **Nuevo contexto `Settings`** con un **catálogo de claves como dato**
  (`SettingCatalog`): cada entrada declara `key`, `group`, `scope`
  (system|tenant|both), `type` (string|bool|int|select|secret|json), `label`,
  ayuda, opciones y la **`configKey`** de la que hereda su default. El catálogo
  como dato (no claves libres) hace que "todo configurable" sea **acotado y
  seguro**: solo se pueden fijar claves conocidas y validadas por tipo.

- **Store cifrado** (`settings`: scope, tenant_id?, key, value). El `value` se
  guarda **cifrado** (puede ser secreto) como JSON. No usa el TenantScope global
  (las filas de sistema — `tenant_id` NULL — y de tenant deben leerse ambas).

- **Resolución con precedencia** en `SettingsRepository`/`Settings`:
  **tenant → sistema → default (config/env)**. Memoizada por request y
  **defensiva**: si el store no está disponible (antes de migrar, `config:cache`
  en deploy) cae al default, así el arranque nunca depende de la tabla.

- **Dos alcances con dos actores**:
  - **Sistema** (defaults de plataforma: selección de proveedor IA/analytics/
    stream/enterprise y sus claves): editable por un **super-admin de
    plataforma** — nueva bandera `users.is_super_admin`, concedida fuera de banda
    (`escenia:super-admin`), nunca mass-assignable ni expuesta a la API de tenant.
    Endpoints `GET/PUT /system/settings` tras el middleware `super.admin`.
  - **Tenant** (overrides propios, p. ej. su clave de IA): editable por el Owner
    (`tenant.manage`). Endpoints `GET/PUT /settings`.

- **Secretos write-only**: nunca se devuelven; la API solo indica `is_set` y su
  valor se enmascara. Un valor vacío al escribir = "no cambiar". Los secretos no
  se escriben jamás al audit trail (solo la lista de claves).

- **Rewire de la selección de proveedores**: `AiServiceProvider`,
  `ScaleServiceProvider`, `EnterpriseServiceProvider` (y el `domain_target` de la
  Fase 13) resuelven su selección/credenciales vía `Settings` con **fallback a
  `config`**, así un operador reconfigura integraciones desde la app sin
  redeploy. Con el store vacío el comportamiento es idéntico al anterior (default
  de config/env).

## Consequences

- Cualquier integración del catálogo se configura desde el sistema; añadir una
  nueva es añadir una entrada al catálogo apuntando a su `configKey`.
- La configuración es multi-tenant desde el diseño: defaults de plataforma +
  overrides por tenant, con precedencia y cifrado de secretos.
- Los proveedores singleton capturan su configuración al resolverse; un cambio de
  settings de sistema aplica en el siguiente arranque/rebind. El **binding por
  tenant en caliente** de los proveedores de IA (para que un tenant use su propia
  clave en el mismo proceso) queda como deuda (TD-045); las integraciones ya
  per-tenant (pagos/SSO) siguen resolviéndose por request con sus modelos.
- Deuda (TD-045): binding per-tenant en caliente de los proveedores singleton;
  rotación/versionado de secretos en el store; caché distribuida de settings
  (hoy memo por request) e invalidación al escribir en multi-instancia.
