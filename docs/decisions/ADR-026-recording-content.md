# ADR-026 — Recording & Content: grabaciones, ISO tracks, transcripts y clips

## Status

Accepted (2026-09-08)

## Context

La Fase 9 convierte los eventos en **contenido**: grabaciones (desde un broadcast
grabado o subida local), **ISO tracks** (pistas aisladas por fuente), transcripts
y un content studio con clips (el editor). Toca por primera vez **object
storage** con URLs firmadas y **Jobs/Workers** (procesos pesados fuera del
request — CLAUDE.md). El media plane (ADR-002/021) ya produce egress con flag de
grabación, y el Outbox (ADR-007) es el backbone de eventos.

## Decision

- **Dominio `Content` nuevo** (tenant-owned, ULID): `Recording` (source
  `broadcast|upload`, status `pending→processing→ready/failed`, disk +
  storage_key, duration/size/format), `RecordingTrack` (ISO: `composite|screen|
  camera|audio`), `Transcript` + `TranscriptSegment`, `Clip`.

- **El binario nunca pasa por Laravel.** Storage detrás de `RecordingStorage`
  (contract): la subida local es un **ticket firmado direct-to-storage**
  (`FakeRecordingStorage` determinista por defecto; `S3RecordingStorage` con
  `temporaryUploadUrl`). El cliente sube al bucket y llama `complete` (que reporta
  metadata y crea la track `composite`). La `playbackUrl` también sale del
  contract (temporal en S3).

- **Transcripción en Job** (`TranscribeRecordingJob`, queued — primer uso de
  Jobs, CLAUDE.md): crea el `Transcript` pending, encola, y el job llama al
  `Transcriber` (contract) y persiste los segmentos → ready/failed. Corre dentro
  del contexto de tenant del transcript. `FakeTranscriber` (default, segmentos
  canónicos) + `HttpTranscriber` (stub real, sin integración). El endpoint
  responde `202 Accepted`.

- **Auto-registro desde broadcast vía Outbox** (extiende TD-010): `StopBroadcast`
  escribe `broadcast.ended` al outbox; el dispatcher (topic→lista de handlers)
  enruta a `RegisterBroadcastRecordingHandler`, que si `record=true` crea la
  `Recording` (processing) + track `composite`, idempotente por broadcast
  session. Decoplado por el backbone; Broadcasting no conoce Content.

- **Content studio / editor**: `Clip` recortado de una recording (rango ms;
  `end > start` o `422 invalid_clip_range`). El render/export del asset recortado
  es futuro.

- **Providers por `config/recordings.php`** (`storage` fake|s3, `transcriber`
  fake|http), bindeados en `ContentServiceProvider` — mismo patrón que media/
  payments; el dominio nunca ve el SDK. RBAC `content.view`/`content.manage` en
  `EventPolicy`. Frontend: métodos `recordings*`/`transcripts*`/`clips*`.

## Consequences

- Los grandes binarios suben directo al object storage; el control plane solo
  emite tickets firmados y valida el `complete`.
- Storage y transcripción son intercambiables (fake/real) sin tocar el dominio.
- El auto-registro por outbox avanza la migración del lifecycle de broadcast al
  Outbox (TD-010).
- Deuda: proveedores reales (S3 firmado, transcriptor HTTP) sin tests de
  integración; el `complete` confía en size/duration del cliente (sin verificar
  el objeto); el asset de egress real (broadcast) no se entrega todavía (falta el
  webhook del provider que marque `ready`); render/export de clips e ISO tracks
  reales pendientes; Jobs requieren worker/Horizon en producción. Ver
  `technical-debt.md` (TD-026..028).
