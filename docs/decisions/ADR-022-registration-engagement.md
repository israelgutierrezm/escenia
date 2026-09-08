# ADR-022 — Webinar: registro público, autenticación de asistente y engagement

## Status

Accepted (2026-09-08)

## Context

La Fase 5 convierte la plataforma en un producto comercializable: un evento con **registro público**, una **audiencia** que asiste, y engagement en vivo (chat, Q&A, polls, recursos descargables). Esto introduce por primera vez un actor que **no es un `User` del tenant** — el asistente — con su propia superficie de API pública, separada del control plane autenticado por cookie de los hosts.

Los recordatorios (notificaciones/scheduling) y la monetización (CTAs/commerce) quedan **fuera de alcance** aquí (Fases 8 y 7).

## Decision

- **Contextos acotados nuevos.** `Registration` (identidad de audiencia: `RegistrationForm`, `Contact`, `Registration`, `Attendee`) y `Engagement` (participación en vivo: `AttendeeSession`, `ChatMessage`, `Question`/`QuestionVote`, `Poll`/`PollOption`/`PollVote`, `Resource`/`ResourceDownload`). Todo es tenant-owned (`BelongsToTenant`) e identificado en público por ULID.

- **Registro público con tenant derivado del evento.** El evento se resuelve **sin scope** por su ULID y el tenant se establece con `TenantContext::runFor` — nunca se confía en un `tenant_id` del request (CLAUDE.md §4, ADR-010), igual que el canje de guest links (ADR-019). `RegisterAttendeeAction` es idempotente por `(event, email)`: reutiliza `Contact` (único por `workspace+email`) y `Registration` (único por `event+contact`), y **rota el join token** del asistente para devolver siempre una credencial válida. El `Contact` es la raíz de un CRM de audiencia reutilizable entre eventos.

- **Autenticación de asistente = token de join.** El asistente se autentica con un token opaco presentado en la cabecera `X-Attendee-Token` (nunca en la URL, para no filtrarlo en logs/historial — security.md). Solo se persiste su **hash SHA-256** (`join_token_hash`, `$hidden`), como los guest links. El middleware `ResolveAttendee` busca el asistente sin scope por hash, establece el tenant desde el propio asistente y expone `AttendeeContext` (singleton scoped). Los endpoints de asistente son públicos + rate-limited (`/api/v1/attend/*`); los de host van bajo `auth:sanctum` + tenant + RBAC.

- **RBAC.** Permisos `engagement.view` / `engagement.manage` (patrón view/manage como studio/broadcast), colgando del `EventPolicy` (`viewEngagement` / `manageEngagement`) porque el evento es la raíz del agregado de engagement. Owner/Admin gestionan; Member ve.

- **Máquina de estados de polls.** `draft → open → closed`, guardada + optimistic locking (compare-and-swap): transición ilegal `422 invalid_poll_transition`, carrera perdida `409 poll_conflict` — mismo patrón que evento/broadcast (ADR-017/021). Se vota **solo** en `open` (`422 poll_not_open`); un voto es único y final por `(poll, attendee)` — el índice único es la fuente de verdad y una segunda emisión es `409 already_voted`.

- **Idempotencia de contadores.** Upvotes de preguntas y descargas de recursos usan una fila única por `(objeto, attendee)` con contador denormalizado que solo avanza en la primera vez (`firstOrCreate` + `wasRecentlyCreated`), de modo que reintentos del cliente no inflan métricas.

- **Presencia.** `AttendeeSession` (join → leave, con `last_seen_at` como heartbeat) es materia prima para analítica de asistencia (Fase 6). `join` es idempotente y hace las veces de heartbeat.

- **Contratos de frontend.** Dos clientes separados (CLAUDE.md §6): el `ApiClient` de host suma registro/engagement; un `AttendeeClient` nuevo cubre la superficie pública + de token. Tipos en `@escenia/types`.

## Consequences

- Existe una superficie pública de asistente con su propio modelo de auth, aislada del control plane de hosts; el tenant siempre se deriva server-side.
- El `Contact` habilita CRM de audiencia y de-duplicación entre eventos desde el día uno.
- Deuda: reminders/notificaciones (Fase 8) y CTAs/commerce (Fase 7) diferidos; el chat es polling/REST — el tiempo real (Reverb) se difiere hasta que exista demanda; los eventos de engagement migrarán al Outbox (ADR-007) cuando haya consumidores (analytics). Ver `technical-debt.md`.
