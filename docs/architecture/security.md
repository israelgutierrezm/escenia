# Security Architecture

## Base

- strict tenant isolation;
- RBAC + scoped authorization;
- secure route bindings;
- rate limiting;
- encrypted secrets;
- signed guest URLs;
- short-lived playback/upload tokens;
- audit logs.

## Secretos

Cifrar:

- OAuth refresh/access tokens;
- stream keys;
- API credentials;
- provider secrets.

Nunca imprimirlos en logs.

## Guest access

Guest link puede incluir:

- expiration;
- revocation;
- single-use;
- optional password;
- event/session binding.

## Enterprise future

Preparar:

- MFA;
- SSO;
- SAML;
- SCIM;
- IP restrictions;
- geo restrictions;
- domain restrictions.

## PII

Clasificar datos personales.

Soportar a futuro:

- export;
- anonymization;
- deletion requests;
- configurable retention.

## Backstage privacy

Ningún servicio de IA, transcript o attendee API debe tener acceso accidental a mensajes/backstage privados.
