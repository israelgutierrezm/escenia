# ADR-035 — SSO completo: SAML, login en la SPA, egress fijado e identidad federada

## Status

Accepted (2026-10-06). Completa ADR-034 (cierra los pendientes de TD-035).

## Context

ADR-034 cerró la toma de cuentas entre tenants, pero dejó SSO sin poder usarse de
punta a punta: no había adaptador SAML, ninguna app consumía el flujo, las
llamadas del servidor a endpoints configurados por el tenant (token, JWKS)
podían apuntar a la red interna (SSRF), un dominio verificado lo quedaba para
siempre aunque cambiara de dueño, y el usuario se resolvía solo por email.

## Decision

- **Egress fijado** (`OutboundUrlGuard`): toda llamada del servidor a una URL
  del IdP resuelve el host, exige que **todas** sus IPs sean públicas
  (`FILTER_FLAG_GLOBAL_RANGE` + rechazo de IPv6 con IPv4 embebida: privadas,
  loopback, link-local/metadata de nube, CGNAT, reservadas) y **fija** la
  conexión a la IP validada (`CURLOPT_RESOLVE`), de modo que un DNS que cambia
  entre la validación y la conexión no sirve (rebinding). Sin redirecciones.
  `ENTERPRISE_EGRESS_ALLOW_PRIVATE` existe solo para un IdP local en desarrollo.
- **Descubrimiento OIDC**: basta issuer + client id/secret; los endpoints que
  falten salen de `{issuer}/.well-known/openid-configuration` (vía el guard,
  cacheado 1 h, aceptado solo si declara el **mismo** issuer — OIDC Discovery
  §4.3). Los endpoints explícitos tienen prioridad.
- **Login SSO en la SPA**: `GET /sso/discover?email=` lista las conexiones
  activas con dominio verificado para ese correo; el login del admin ofrece
  «Continuar con SSO» y la ruta `/sso/:connection/callback` completa el flujo
  (OIDC canjea el `code`; SAML llega con la sesión ya iniciada). El
  `redirect_uri` debe ser una app propia (regla `FirstPartyUrl`: host:puerto en
  los dominios *stateful* de Sanctum) — nunca una redirección abierta.
- **SAML 2.0** (`SamlIdentityProvider`, `onelogin/php-saml` detrás del
  adaptador — ADR-008): SP-initiated, AuthnRequest por HTTP-Redirect con
  `ID = '_' . nonce` del intento, respuesta por HTTP-POST al ACS. Validación en
  modo estricto de la librería — firma con el certificado del IdP (sobre la
  respuesta o la aserción; sin firma se rechaza), esquema XSD, InResponseTo,
  audiencia, issuer, destino/recipient, ventanas de tiempo — y un pre-chequeo
  propio que **rechaza SHA-1** (la librería lo aceptaría). Los "current URL" de
  onelogin se fijan a la URL de ACS que publicamos (derivada de `APP_URL`, no del
  `Host` de la petición). El ACS es una ruta con sesión (grupo `web`, exenta de
  CSRF: la protegen la respuesta firmada, el `RelayState` de un solo uso y la
  cookie de enlace, que para SAML va `SameSite=None; Secure` porque el IdP
  vuelve con un POST cross-site). Sin SSO iniciado por el IdP (no solicitado).
  Metadata del SP en `/api/v1/sso/{id}/saml/metadata` (= entity ID). El driver
  real pasa a llamarse `real` (`oidc` sigue aceptado) y enruta por protocolo.
- **Identidad federada `(conexión, sub)`** (`sso_identities`): el login se
  resuelve primero por el sujeto del IdP; un sujeto nuevo se enlaza por email,
  salvo que esa cuenta ya esté enlazada a **otro** sujeto en la conexión —
  correo reasignado en el IdP —, que se rechaza y audita (`subject_mismatch`).
  Un usuario tiene como mucho un sujeto por conexión (índice único).
- **Re-verificación del dominio**: `enterprise:reverify-sso-domains` (cada
  hora) encola `ReverifySsoDomainJob` para los dominios no comprobados en el
  último día. Si el TXT falla se abre una gracia
  (`ENTERPRISE_SSO_DOMAIN_GRACE_HOURS`, 72 h) y se audita; si sigue fallando, se
  revoca la verificación (y con ella los logins). **Unicidad**: un dominio solo
  puede estar verificado por una organización a la vez (bajo lock).

## Consequences

- SSO es utilizable de punta a punta (OIDC y SAML) desde el admin, sin que el
  servidor llame a la red interna ni un correo reasignado herede una cuenta.
- Nueva dependencia: `onelogin/php-saml` (+ `robrichards/xmlseclibs`), aislada
  en Infrastructure.
- SAML necesita HTTPS en el despliegue (cookie `SameSite=None; Secure`).
- **Deuda** (TD-035): prueba contra un IdP real (runbook en
  `docs/architecture/sso-real-idp.md`; bloqueada en este equipo por Docker/WSL);
  login SSO en `apps/studio`; UI para desvincular una identidad federada (hoy un
  usuario recreado en el IdP queda bloqueado hasta intervención manual); SAML
  sin aserciones cifradas, sin AuthnRequest firmado ni Single Logout.
