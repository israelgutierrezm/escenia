# ADR-018 — Implementación de la abstracción del media provider (Studio)

## Status

Accepted (2026-09-08). Actualizado el 2026-10-22: con `firebase/php-jwt` 7 el secreto de LiveKit debe tener al menos 32 bytes.

## Context

ADR-002 y ADR-008 exigen que el dominio hable con un `MediaProviderContract` y nunca con el SDK de LiveKit. La Fase 2 (Studio MVP) es la primera que necesita el media plane, así que aquí se materializa la abstracción.

## Decision

- **`MediaProviderContract`** (`App\Domain\Media\Contracts`) con Value Objects agnósticos: `RoomSpec`, `RoomHandle`, `ParticipantIdentity`, `ParticipantGrants`, `AccessToken`. Ningún tipo del SDK cruza la frontera. Cubre el subconjunto interactivo (rooms, participantes, tokens) que necesita Studio; ingress/egress/recording/health llegan en Fase 4.
- Dos implementaciones (`App\Infrastructure\Media`):
  - **`FakeMediaProvider`** — determinista y sin red; default en local y tests (la suite no necesita Internet).
  - **`LiveKitMediaProvider`** — emisión real de access tokens (JWT HS256 firmado con `firebase/php-jwt`, testeable sin servidor); `provisionRoom` devuelve un handle (LiveKit auto-crea la room al unirse); `removeParticipant`/`closeRoom` llaman la RoomService API best-effort.
  - Selección por `config/media.php` (`MEDIA_PROVIDER`), enlazada en `MediaServiceProvider`.
- Los grants de media se derivan de **rol + stage** en `ParticipantGrantPolicy` (dominio), fuera de los adaptadores y del VO genérico.

## Consequences

- El dominio nunca importa tipos del SDK; el proveedor es intercambiable/combinable.
- La emisión de tokens es determinista y testeable; la suite usa el fake.
- Deuda: las llamadas a la RoomService API de LiveKit no se prueban contra infraestructura real (`technical-debt.md`).
- Desde `firebase/php-jwt` 7 (2026-10-22, CVE-2025-45769) la librería rechaza
  claves HMAC más cortas que el hash (RFC 7518 §3.2): el secreto de LiveKit debe
  tener al menos 32 bytes. `LiveKitMediaProvider` lo comprueba al construirse,
  así que una configuración débil falla de inmediato en vez de en la primera
  unión a una sala. `livekit-server --dev` usa `secret`: en local hay que darle
  una clave larga con `--keys`.
