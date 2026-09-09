# ADR-028 — Education: assessments, reglas de finalización y certificación

## Status

Accepted (2026-09-08)

## Context

La Fase 11 convierte un evento en un curso evaluable y certificable: **quizzes
auto-corregidos**, **requisitos de finalización** (minutos vistos y/o aprobar el
quiz) y **certificados verificables públicamente**. Reusa la audiencia
(asistentes, Fase 5), la presencia (`attendee_sessions`, Fase 6) y el **Outbox**
— ADR-007 lista "certificate issue" como su caso canónico.

## Decision

- **Dominio `Education` nuevo** (tenant-owned, ULID): `Assessment` (passing_score,
  publicado), `AssessmentQuestion` (`options` JSON con flag `correct`),
  `AssessmentSubmission` (único por `(assessment, attendee)`), `CompletionRule`
  (por evento: `min_watch_seconds?`, `require_assessment`), `Certificate` (código
  público de verificación, único por `(event, attendee)`).

- **La clave de respuestas nunca sale del servidor.** El asistente ve el
  assessment publicado con un Resource separado (`AttendeeAssessmentResource`)
  que expone solo `{key, label}` de cada opción — **jamás** `correct`. La
  corrección es server-side (`AssessmentScorer`: coincidencia exacta de las
  opciones correctas por pregunta → sus points; `passed = score ≥ passing_score`).

- **Certificación por Outbox** (6º topic, caso de ADR-007): `TakeAssessmentAction`
  (asistente, token-auth) corrige y escribe `assessment.submitted`;
  `IssueCertificateHandler` evalúa `CertificationService::isEligible` (minutos
  vistos derivados de `attendee_sessions` + assessment aprobado si se requiere) y
  **emite el certificado si cumple** (idempotente por `(event, attendee)`). Para
  certificación **solo por asistencia** (sin assessment que reaccione), un
  endpoint host recorre los asistentes con la misma lógica.

- **Verificación pública**: `GET /certificates/verify/{code}` (sin auth) resuelve
  el certificado **sin scope** por su código opaco y expone solo nombre del
  receptor, título del evento y fecha de emisión — nada más de PII.

- **Presencia → minutos vistos**: suma de `(left_at ?? last_seen_at ?? joined_at)
  − joined_at` sobre las sesiones; una sesión abierta se acota a su último
  heartbeat.

- **RBAC nuevo** `education.view` / `education.manage` (capacidad distinta,
  gateable por plan a futuro). Endpoints de asistente con `attendee` middleware;
  verificación pública; host bajo auth+tenant+RBAC. Frontend: métodos host
  (`saveAssessment`/`completionRule`/`submissions`/`certificates`/
  `issueCertificates`) + `AttendeeClient` (`assessment`/`submitAssessment`/
  `verifyCertificate`).

## Consequences

- Un evento es ahora un curso con evaluación objetiva y credencial verificable;
  la clave de respuestas está protegida por diseño.
- La emisión de certificados reusa el Outbox, consolidándolo como backbone.
- Deuda: render/PDF del certificado y plantilla de marca; un solo intento por
  assessment (sin reintentos/retomas); certificación por asistencia se dispara a
  mano (o podría colgar de `event.ended`); tipos de pregunta abiertos
  (texto libre) no soportados; gating de Education por entitlement. Ver
  `technical-debt.md` (TD-031..032).
