# ADR-036 — SAML firmado y cifrado, cierre de sesión único e interoperabilidad SSO

## Status

Accepted (2026-10-07). Completa ADR-034/035.

## Context

Tras ADR-035 seguían pendientes en TD-035:
- los extras SAML que exigen muchos clientes enterprise: AuthnRequest firmado,
  aserciones cifradas y cierre de sesión único;
- una salida para el usuario cuya cuenta del IdP se recreó (un sujeto nuevo
  queda bloqueado por la regla de correo reasignado);
- el login SSO en `apps/studio`;
- una prueba contra un IdP real, imposible aquí con Keycloak: no hay Docker
  (WSL sin distro) ni Java.

## Decision

- **Credenciales del SP por conexión SAML**: certificado autofirmado (10 años:
  SAML confía en el certificado fijado, no en una cadena de CA) y clave privada
  **cifrada en reposo**, en columnas propias (`sp_certificate`, `sp_private_key`).
  No van en `config`, que se reemplaza entera al editar. Se emiten al crear la
  conexión (`ServiceProviderCredentialIssuer` → `OpensslCredentialIssuer`, con
  un `openssl.cnf` mínimo propio para funcionar también donde PHP no trae uno) y
  se rotan con `POST /enterprise/sso-connections/{id}/saml/credentials`. La
  metadata del SP publica el certificado para firma y cifrado.
- **Opciones de la conexión** (`config`): `sign_requests` firma el AuthnRequest y
  los mensajes de logout (binding HTTP-Redirect: la firma cubre exactamente la
  query enviada); `encrypt_assertions` exige aserciones cifradas; `idp_slo_url`
  activa el cierre de sesión único. Pedir firma o cifrado sin credenciales falla
  cerrado.
- **Algoritmos débiles**: además de la validación estricta de onelogin, se
  rechazan firmas/digests SHA-1 y el transporte de clave RSA-1.5, **también
  dentro de la aserción descifrada**. Se ignora el SHA-1 propio de RSA-OAEP, que
  vive fuera de las firmas.
- **Cierre de sesión único** (front-channel, `SingleLogoutProvider` →
  `SamlSingleLogout`): al iniciar sesión por SAML se guarda en la sesión el
  NameID y el SessionIndex. Al salir, `POST /auth/logout` devuelve
  `sso_logout_url`, la SPA lleva el navegador al IdP y éste vuelve a
  `GET /api/v1/sso/{id}/slo` con su LogoutResponse; de ahí se va a `return_to`
  (solo apps propias) con `logout=ok`, o `logout=partial` si el IdP no confirmó
  el cierre: la sesión de Escenia ya está cerrada, pero el login avisa de que
  la del IdP puede seguir abierta (importa en un equipo compartido). Un
  LogoutRequest del IdP cierra la sesión de este
  navegador **solo si** viene firmado con un algoritmo fuerte (SHA-1 o un
  `SigAlg` ausente se rechazan; la firma se verifica sobre la query original) y
  nombra el NameID de esa sesión.
- **Identidades vinculadas**: `GET/DELETE /enterprise/sso-connections/{id}/identities`
  y su panel en el admin. Desvincular es la salida para una cuenta del IdP
  recreada: el siguiente login enlaza el nuevo sujeto por correo.
- **Login SSO en studio**, con la misma lógica que el admin, compartida en
  `@escenia/api-client` (`redirectToSso`, `finishSsoLogin`).
- **Interoperabilidad sin Docker** (`tools/sso-interop`): Escenia corre de verdad
  (`php -S` sobre SQLite aislada) frente a `oidc-provider` (OP certificado) y
  `samlify` (IdP SAML, valida contra los XSD). 14/14 comprobaciones. La prueba
  destapó dos fallos reales del adaptador OIDC, ya corregidos:
  `client_secret_basic` (el método por defecto de OIDC; ahora configurable con
  ese valor por defecto) y los claims que un IdP conforme entrega **solo por
  UserInfo** (ahora se consultan, exigiendo el mismo `sub`).

## Consequences

- Se cubre lo que suelen exigir las listas de seguridad enterprise: request
  firmado, aserción cifrada y SLO.
- El IdP debe configurarse para **firmar los mensajes de logout** (la metadata
  SAML no puede expresarlo). Si firma la respuesta completa, debe **cifrar antes
  de firmar** e incluir `AuthnStatement`, como hacen los IdPs reales.
- SLO solo por front-channel: no hay back-channel SOAP ni SSO iniciado por el
  IdP (no solicitado; por diseño).
- Rotar las credenciales obliga al IdP a reimportar la metadata.
- **Pendiente** (TD-035): correr el runbook contra un IdP comercial (Okta, Entra,
  Keycloak) cuando haya red y Docker; las implementaciones independientes ya
  interoperan.
