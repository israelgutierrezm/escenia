# ADR-034 — Endurecimiento de SSO: dominio verificado, fail-closed y OIDC validado

## Status

Accepted (2026-10-05). Enmienda la sección SSO de ADR-030.

## Context

Al revisar TD-035 ("el stub OIDC no valida la firma del `id_token`") el problema
resultó más grave que la firma:

1. **Toma de cuentas entre tenants.** `CompleteSsoLoginAction` enlazaba el
   email que afirma el IdP con **cualquier** `User` global y el controlador abría
   sesión como ese usuario. El IdP lo configura el propio tenant, y **todo
   registro crea un tenant** cuyo dueño tiene `tenant.manage`: cualquiera podía
   crear una conexión, afirmar `victima@otra.com` y obtener una sesión de la
   víctima — con acceso a *sus* tenants. Validar la firma no lo arregla: el
   atacante controla también el issuer y el JWKS.
2. **El default era el fake.** Sin configurar `enterprise.identity_provider`, se
   enlazaba `FakeIdentityProvider`, que acepta el `email` del body del callback:
   `POST /sso/{conexión}/callback {email}` bastaba para entrar como cualquiera.
   Lo mismo con `FakeDomainVerifier` (aprueba cualquier dominio).
3. **Sin `state` efectivo, nonce ni PKCE.** El `state` se generaba y devolvía
   pero nunca se guardaba ni se validaba (login CSRF, inyección de código).
4. **Sin validación del `id_token`** (TD-035 original).

## Decision

- **Una conexión solo responde por correos de su dominio verificado.**
  `SsoConnection` gana `domain_verification_token` + `domain_verified_at`; el
  tenant prueba control del dominio publicando el TXT
  `_escenia-sso.<dominio>` (`VerifySsoDomainAction`, mismo `DomainVerifier` que
  los dominios custom, ahora generalizado a un `DnsChallenge`). Al completar el
  login, `SsoConnection::vouchesFor($email)` exige dominio verificado y email de
  ese dominio exacto; si no, 401 y auditoría `enterprise.sso.login_rejected`.
  Es el modelo de Okta/WorkOS: quien controla el DNS del dominio ya controla sus
  buzones (podría resetear contraseñas), así que el tenant no gana poder nuevo.
  Cambiar el dominio reinicia la verificación; `domain` pasa a ser obligatorio
  al crear (sin dominio una conexión no puede iniciar sesión a nadie).
- **Fail-closed en producción.** `fake` solo se enlaza fuera de producción; en
  producción (o con un driver desconocido) se enlazan `DisabledIdentityProvider`
  (ningún login SSO) y `DisabledDomainVerifier` (nunca verifica), con un warning
  en el log. Configurar SSO es activar `oidc`/`dns`, no confiar en un default.
- **Intento de login del lado del servidor.** `StartSsoLoginAction` emite un
  `SsoLoginAttempt` — `state`, `nonce`, verifier PKCE (S256) y un *binding* — y
  lo guarda en caché 10 min (`SsoLoginAttempts`). El binding viaja en una cookie
  HttpOnly `escenia_sso` (path `/api/v1/sso`, SameSite/secure de la sesión). El
  callback debe presentar el `state` **desde el mismo navegador** (hash del
  binding), para **la misma conexión** y **una sola vez** (`Cache::add`
  atómico); un intento fallido no consume el `state`. `redirect_uri`, nonce y
  verifier salen del intento, nunca del body.
- **`id_token` validado** (`OidcIdTokenValidator`, php-jwt detrás del
  adaptador): solo algoritmos asimétricos (`RS256/384/512`, `ES256/384`; nunca
  `none`/`HS*`), JWKS con caché de 1 h y **un** refetch ante `kid` desconocido
  (rotación, con cooldown); a los JWK sin `alg` (Entra ID) se les fija el `alg`
  del token solo dentro de su familia. Claims: `iss` exacto, `aud` contiene el
  `client_id`, `azp` obligatorio si hay varias audiencias, `exp`/`iat`
  obligatorios (leeway 60 s), `nonce` del intento, `email_verified` si viene.
  El código se canjea con `code_verifier`, sin seguir redirecciones y con
  timeout. Las conexiones SAML se rechazan hasta que exista su adaptador.
- **Endpoints del IdP solo `https` en producción** (se llaman desde el
  servidor). Configuración incompleta → falla cerrado antes de llamar al IdP.

## Consequences

- Cierra la toma de cuentas: ni el fake ni un IdP malicioso pueden afirmar
  identidades fuera del dominio que el tenant demostró controlar.
- Las conexiones existentes con dominio reciben token y quedan **sin
  verificar**: sus logins fallan hasta verificar (intencional; solo había datos
  de desarrollo). Las que no tienen dominio no pueden iniciar sesión.
- El contrato `IdentityProvider` cambia a DTOs (`SsoAuthorizationRequest`,
  `SsoCallback`) y `DomainVerifier` recibe un `DnsChallenge`.
- El callback exige `state` + cookie: el futuro login SSO de la SPA debe llamar
  a `GET /sso/{id}?redirect_uri=<ruta de la SPA>` y luego al callback con
  `credentials: 'include'`.
- **Deuda** (TD-035): adaptador SAML con verificación de aserciones; descubrimiento
  OIDC (`.well-known`); login SSO en la SPA (descubrimiento por dominio de email
  + página de callback); controles de egress contra SSRF ciego (bloquear IPs
  privadas tras resolver DNS); re-verificación periódica del dominio y unicidad
  entre tenants; identidad federada por `(conexión, sub)`; prueba contra un IdP
  real.
