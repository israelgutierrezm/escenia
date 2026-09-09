# ADR-029 — Enterprise Events: agenda multi-track, sponsors/expo y gamification

## Status

Accepted (2026-09-08)

## Context

La Fase 12 escala un evento a "conferencia": agenda multi-sesión con tracks,
sponsors con expo y captura de leads, y gamification que conecta la
participación. El roadmap lista además networking, virtual venue y hybrid;
construir los siete sub-módulos de golpe sería enorme. Se elige un **corte
coherente de tres contextos** (estructura + monetización + engagement) y se
difiere el resto con deuda explícita.

## Decision

- **Tres bounded contexts nuevos** (tenant-owned, ULID), en línea con el monolito
  modular:
  - `Agenda`: `Track` (por evento); se **extienden `event_sessions`** (aditivo:
    `track_id?`, `room?`, `capacity?`, `registered_count`); `SessionRegistration`
    (agenda personal del asistente, único por `(session, attendee)`, con **cupo**
    — `422 session_full`, idempotente).
  - `Sponsorship`: `Sponsor` (tier), `Booth` (ligado a sponsor), `BoothLead`
    (visita del asistente = **lead** para el sponsor, único por `(booth, attendee)`).
  - `Gamification`: `PointsAward` (ledger append-only, único por
    `(attendee, action, subject)` → idempotente), valores fijos, y `LeaderboardService`.

- **Gamification como tejido conectivo**: `AwardPoints` se invoca **dentro** de
  las acciones que ganan puntos (inscribir sesión = 10, visitar booth = 5); una
  escritura barata e idempotente. Ni la inscripción ni la visita repetidas
  duplican puntos o contadores.

- **Superficie de asistente** (token): explorar agenda + armar la propia,
  explorar expo + visitar booths (→ lead + puntos), ver sus puntos + leaderboard.
  **Superficie host** (auth+tenant+RBAC): tracks, asignar agenda a sesiones,
  sponsors/booths, leer leads, leaderboard.

- **RBAC único `enterprise.view`/`enterprise.manage`** para los tres módulos
  (evita sprawl de permisos; los tres son "gestión del evento enterprise"). Los
  leads son del tenant organizador y quedan protegidos por `enterprise.view`; el
  asistente nunca ve leads de otros.

- **Contadores denormalizados** (`registered_count`, `leads_count`) mantenidos en
  la acción; los índices únicos garantizan que un asistente cuente una vez.

## Consequences

- Un evento es ahora una conferencia multi-track monetizada (sponsors/expo) y
  gamificada, toda sobre la superficie de asistente + presencia existentes.
- El patrón de contexto acotado se sostiene sin acoplar Agenda/Sponsorship/
  Gamification entre sí (solo Gamification es invocado por las otras dos).
- Deuda: **networking** (conexiones y meetings 1:1), **virtual venue espacial**
  (la parte estructural la cubren sesiones + `room`; el layer espacial/media es
  futuro) y **hybrid check-in** (físico) quedan sin construir; los puntos son
  valores fijos (configurables por evento = futuro); el cupo de sesión puede
  hacer leve oversell bajo concurrencia (como tickets); el leaderboard agrega en
  SQL O(n). Ver `technical-debt.md` (TD-033..034).
