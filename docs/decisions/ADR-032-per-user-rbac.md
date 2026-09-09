# ADR-032 — Roles personalizados y permisos por usuario

## Status

Accepted (2026-09-09)

## Context

Hasta ahora el RBAC (ADR-011) tenía un catálogo fijo de tres roles
(owner/admin/member) como plantillas globales de Spatie (team = tenant), sin
forma de crear roles a medida ni de conceder permisos a un usuario concreto. Se
pide **roles y permisos por usuario**: que cada tenant defina sus propios roles
y ajuste el acceso de cada miembro.

## Decision

- **Se construye sobre Spatie con teams** (ya habilitado, `team_foreign_key =
  tenant_id`); no se añaden tablas nuevas. El catálogo de permisos sigue siendo
  el enum `Permission` (única fuente de verdad; nada de strings mágicos).

- **Roles de sistema vs personalizados**:
  - `owner`/`admin`/`member` son **plantillas globales** (`tenant_id` NULL),
    inmutables: no se editan, borran ni se crean con esos nombres.
  - Los **roles personalizados** son propios del tenant (`tenant_id` = tenant),
    con CRUD y un set de permisos elegido del catálogo. Se resuelven por nombre
    dentro del tenant (los de sistema no se resuelven ahí → protegidos).

- **Tres niveles de acceso por usuario**, todos vía Spatie con el team del
  tenant fijado por `ResolveTenant`:
  1. **Rol base** (`membership_role`): la fuente autoritativa del "tier" del
     miembro (`TenantMembership.role`), espejada a la asignación del rol de
     sistema en Spatie. Se cambia en `PUT /members/{user}/membership-role`.
  2. **Roles personalizados** (aditivos): `PUT /members/{user}/roles` sincroniza
     el conjunto de roles a medida, preservando el rol base.
  3. **Permisos directos** (aditivos): `PUT /members/{user}/permissions`
     sincroniza los permisos concedidos directamente al usuario.
  El acceso efectivo es la unión de todos.

- **Guardas anti-escalada** (`AccessGuard`), porque `members.manage` lo tienen
  Owner y Admin:
  - Un actor solo puede conceder/asignar **permisos que él mismo posee**
    (subconjunto de sus permisos efectivos) → `403 privilege_escalation`.
  - Tocar el tier **owner** (asignar/quitar) exige ser owner (`tenant.manage`).
  - **No se puede degradar al último owner** (evita lockout del tenant).
  - Los roles de sistema no se gestionan como personalizados →
    `422 system_role`.

- **Superficie host** (`members.manage`): catálogo de permisos agrupado,
  CRUD de roles, listado de miembros, detalle de acceso efectivo por miembro, y
  los tres endpoints de sincronización. Todas las mutaciones auditadas
  (`rbac.*`). Contratos de frontend en sync.

## Consequences

- Cada tenant modela su propia estructura de acceso sin tocar código ni el
  catálogo de permisos; la autorización sigue resolviéndose por Spatie
  (`->can()`), ahora con roles a medida + grants directos.
- El `membership_role` permanece como tier base único y coherente; los roles
  personalizados y los permisos directos solo **añaden** acceso (Spatie no
  modela denegaciones), lo que cubre el caso común (elevar) sin introducir
  permisos negativos.
- Deuda (TD-044): no hay permisos "negativos"/denegación por usuario ni
  jerarquía de roles; el borrado de un rol en uso quita sus asignaciones (cascada
  de Spatie) sin aviso previo; falta invitación/gestión del ciclo de vida del
  miembro (alta/baja) más allá de cambiar su rol.
