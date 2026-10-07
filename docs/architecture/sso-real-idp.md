# Probar SSO contra un IdP real (Keycloak)

La suite cubre OIDC y SAML con un IdP falso que firma tokens y respuestas **de
verdad** (claves generadas en cada corrida). Esta guía es para la prueba manual
contra un IdP real, pendiente en TD-035. En el equipo de desarrollo actual está
bloqueada porque Docker Desktop no tiene distro WSL.

## 1. Levantar Keycloak

```bash
docker run --name keycloak -p 8080:8080 -e KC_BOOTSTRAP_ADMIN_USERNAME=admin -e KC_BOOTSTRAP_ADMIN_PASSWORD=admin quay.io/keycloak/keycloak:26.0 start-dev
```

En `http://localhost:8080` (admin/admin): crea el realm `escenia` y un usuario
con email `dev@acme.com`, **Email verified** activado y una contraseña.

## 2. Configurar Escenia (`.env` de desarrollo)

```dotenv
ENTERPRISE_IDENTITY_PROVIDER=real
ENTERPRISE_DOMAIN_VERIFIER=fake        # en dev el TXT se da por publicado
ENTERPRISE_EGRESS_ALLOW_PRIVATE=true   # Keycloak está en localhost; NUNCA en producción
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5180,127.0.0.1,127.0.0.1:5180
```

## 3. OIDC

1. En Keycloak: *Clients → Create client* → OpenID Connect, Client ID
   `escenia`, **Client authentication** activado. *Valid redirect URIs*:
   `http://localhost:5180/sso/*`. Copia el secreto (*Credentials*).
2. En el admin, *Enterprise → SSO → Nueva conexión*: OIDC, dominio `acme.com`,
   configuración:
   ```json
   { "issuer": "http://localhost:8080/realms/escenia", "client_id": "escenia", "client_secret": "…" }
   ```
   Los endpoints se descubren solos. Pulsa **Verificar dominio**.
3. Cierra sesión, escribe `dev@acme.com` y pulsa **Continuar con SSO**.

**Esperado**: Keycloak pide la contraseña y vuelves al resumen con sesión
iniciada. En auditoría aparece `enterprise.sso.login`, y en `sso_identities` el
`sub` de Keycloak enlazado al usuario.

## 4. SAML (requiere HTTPS)

La cookie de enlace de SAML es `SameSite=None; Secure`. Sirve Escenia por HTTPS,
por ejemplo con un proxy TLS local delante de `php artisan serve`, y pon esa URL
en `APP_URL`.

1. En el admin crea una conexión SAML (dominio `acme.com`). La tarjeta muestra el
   **Entity ID**, la **URL de ACS** y la **metadata del SP**.
2. En Keycloak: *Clients → Import client* con la metadata del SP (o créalo con
   *Client ID* = Entity ID y *Valid redirect URIs* = URL de ACS). Activa la firma
   de aserciones o del documento, con RSA_SHA256.
3. Configuración de la conexión:
   ```json
   {
     "idp_entity_id": "http://localhost:8080/realms/escenia",
     "idp_sso_url": "http://localhost:8080/realms/escenia/protocol/saml",
     "idp_x509_cert": "<certificado RS256 de Realm settings → Keys>"
   }
   ```
   Si el correo no viaja en el NameID, añade un mapper de atributo `email` (o
   fija `email_attribute`).
4. **Continuar con SSO** con `dev@acme.com`.

**Esperado**: vuelves a `/sso/{id}/callback?status=ok` y luego al resumen. Una
firma SHA-1, otra audiencia o un correo fuera de `acme.com` terminan en
`?error=sso_failed`; el motivo queda en el log (`SAML response rejected.`).
