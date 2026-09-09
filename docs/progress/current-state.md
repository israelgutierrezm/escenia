# Current State

## Fase actual

**Fase 0 — Foundation — COMPLETED** (2026-09-08), en `main`.

**Fase 1 — Event Core — COMPLETED** (2026-09-08), mergeada a `main` (incluye optimistic locking de transiciones).

**Fase 2 — Studio MVP — COMPLETED** (2026-09-08), mergeada a `main`.

**Fase 3 — Production Engine — COMPLETED** (2026-09-08), mergeada a `main`.

**Fase 4 — Broadcast — COMPLETED** (2026-09-08), mergeada a `main`.

**Fase 5 — Webinar — COMPLETED** (2026-09-08), en `main` (hito comercializable).

**Fase 6 — Analytics — COMPLETED** (2026-09-08), en `main`.

**Fase 7 — Commerce — COMPLETED** (2026-09-08), en `main` (incluye el Outbox de ADR-007).

**Fase 8 — Automation — COMPLETED** (2026-09-08), en `main`.

**Fase 9 — Recording & Content — COMPLETED** (2026-09-08), en `main`. No avanzar a Fase 10 — AI sin instrucción explícita.

## Stack instalado

- **Backend**: Laravel 12.69.1 sobre PHP 8.3.6; MySQL 8.3 (InnoDB, `utf8mb4`); Laravel Sanctum 4.3; Spatie Laravel Permission 8.3 (teams). Redis vía `predis` (opcional en local). Horizon/Reverb aún no instalados (documentados para fases posteriores).
- **Frontend**: monorepo pnpm — Vue 3.5 + TypeScript strict + Pinia + Vue Router + Tailwind 3 + Vitest. `apps/admin` + `packages/{types,api-client,ui}`.
- **Calidad**: Laravel Pint, Larastan/PHPStan nivel 6, Pest (SQLite in-memory); ESLint (flat) + vue-tsc + Prettier.

## Implementado en Foundation

### Backend (`app/`)
- Estructura modular `Domain / Application / Infrastructure / Http`.
- Identidad pública **BIGINT interno + ULID** (`HasPublicId`) — ADR-004.
- Multi-tenancy: `TenantContext`, middleware `ResolveTenant`/`RequireTenant`, `BelongsToTenant` + `TenantScope` (fail-closed) — ADR-010.
- Auth Sanctum cookie SPA: `register` / `login` / `logout` / `me`.
- RBAC Spatie **teams = tenant**, `Permission` enum + `RoleCatalog` (datos), seeder — ADR-011.
- Feature flags (`FeatureFlagResolver`, precedencia user→workspace→tenant→global) + Entitlements (`plans` como datos, `EntitlementResolver`) — ADR-013.
- Auditoría append-only (`AuditLogger`) + logging estructurado con `request_id`/`correlation_id`/`tenant_id`/`user_id` — ADR-015.
- API `/api/v1`: error envelope consistente, rate limiting, Resources / Form Requests / Policies / Actions / DTOs / Enums / Value Object `Money` — ADR-012.
- Tablas: `users`, `tenants`, `tenant_memberships`, `workspaces`, `workspace_memberships`, `plans`, `feature_flags`, `audit_logs`, `personal_access_tokens`, tablas Spatie (con `tenant_id`).

### Event Core (Fase 1 — `app/Domain/Events`, `app/Application/Events`)
- Agregado `Event` con **composición por capacidades** (`event_capabilities`) gated por entitlements — ADR-016.
- **Máquina de estados** guardada (`draft→scheduled→live→ended→archived`, `canceled`) con `TransitionEventAction`, domain event `EventStatusChanged` y auditoría — ADR-017.
- Sub-entidades: `event_sessions`, `event_speakers`, `event_schedule_items`; `event_templates` (sistema + tenant) con `CreateEventAction`.
- Permisos `events.*` (RBAC), `EventPolicy`, API `/api/v1/events` (CRUD + transition + capabilities + sessions/speakers/schedule + templates).
- Tablas: `events`, `event_capabilities`, `event_sessions`, `event_speakers`, `event_schedule_items`, `event_templates`.
- Contratos de frontend en sync: tipos de Event en `@escenia/types` + métodos en `@escenia/api-client`.

### Studio MVP (Fase 2 — `app/Domain/Studio`, `app/Domain/Media`, `app/Application/Studio`, `app/Infrastructure/Media`)
- **Media plane** detrás de `MediaProviderContract` + VOs agnósticos; `FakeMediaProvider` (default) y `LiveKitMediaProvider` (tokens JWT reales) por `config/media.php` — ADR-018. El dominio nunca ve el SDK.
- `studios`, `studio_sessions`, `studio_participants`, `studio_guest_links`; start/end de sesión provisiona/cierra room vía provider.
- **Ciclo de vida del participante** (invited→green_room→backstage→stage→left) con máquina de estados guardada + optimistic locking (409) — ADR-019; grants de media por rol+stage (`ParticipantGrantPolicy`).
- **Guest links** firmados (hash en DB), con expiración/single-use/revocación; canje en flujo **público** (`/studio/guest/{token}/join`) con tenant derivado del link (`TenantContext::runFor`).
- Permisos `studio.*` + `StudioPolicy`; emisión de access tokens a los participantes.
- Contratos de frontend de Studio en sync (`@escenia/types` + `@escenia/api-client`).

### Production Engine (Fase 3 — `app/Domain/Production`, `app/Application/Production`, `app/Infrastructure/Production`)
- **Escenas versionadas** (`scenes` + `scene_versions` inmutables con `schema_version`) y `SceneDefinitionMigrator` para abrir diseños antiguos — ADR-006/020.
- **Vision mixer**: preview/program en el studio (alter aditivo), `TakeSceneAction` + domain event `SceneTaken`.
- **Run of show** ordenable y **brand kits** (workspace-level, un default).
- Permisos `production.*` + StudioPolicy (`viewProduction`/`produce`); contratos de frontend en sync.
- Tablas: `scenes`, `scene_versions`, `brand_kits`, `run_of_show_items` (+ `studios.preview_scene_id`/`program_scene_id`).

### Broadcast (Fase 4 — `app/Domain/Broadcasting`, `app/Application/Broadcasting`)
- **Egress** detrás de `MediaEgressProvider` (fake + LiveKit), misma instancia que el room/token provider — ADR-021. Laravel nunca transporta el stream.
- **Stream keys cifradas** en reposo (`encrypted`), ocultas de la serialización y jamás en logs; los Resources nunca las exponen.
- **Ciclo de vida** (idle→starting→live→ended, failed) con máquina de estados guardada + optimistic locking (409); `StartBroadcast` provisiona egress **multistream** + grabación (flag), `StopBroadcast` lo detiene; health reportable.
- Permisos `broadcast.*` + StudioPolicy; contratos de frontend en sync.
- Tablas: `stream_destinations`, `broadcast_sessions`, `broadcast_destinations`.

### Webinar (Fase 5 — `app/Domain/{Registration,Engagement}`, `app/Application/{Registration,Engagement}`)
- **Registro público**: `RegistrationForm` por evento (fields custom validados), `RegisterAttendeeAction` idempotente por `(event, email)` que crea/reutiliza `Contact` (CRM de audiencia, único por `workspace+email`) + `Registration` y **rota el join token** del `Attendee`. Evento resuelto sin scope; tenant derivado con `TenantContext::runFor` (nunca del request) — ADR-022.
- **Autenticación de asistente**: token de join en cabecera `X-Attendee-Token` (solo hash SHA-256 en DB, `$hidden`), middleware `ResolveAttendee` + `AttendeeContext`. Superficie pública rate-limited `/api/v1/attend/*`; host bajo `auth:sanctum` + tenant + RBAC.
- **Engagement en vivo**: chat (asistente/host, `author_name` denormalizado), Q&A (asistente pregunta/upvota idempotente, host responde), **polls** con máquina de estados guardada `draft→open→closed` + optimistic locking (`422 invalid_poll_transition` / `409 poll_conflict`), voto único y final por `(poll, attendee)` (`409 already_voted`, `422 poll_not_open`), recursos descargables con tally idempotente, y presencia (`AttendeeSession` join/heartbeat/leave).
- Permisos `engagement.view` / `engagement.manage` colgando de `EventPolicy` (`viewEngagement` / `manageEngagement`).
- Contratos de frontend: `ApiClient` de host ampliado + **`AttendeeClient`** nuevo (superficie pública + token) en `@escenia/api-client`; tipos en `@escenia/types`.
- Tablas: `registration_forms`, `contacts`, `registrations`, `attendees`, `attendee_sessions`, `chat_messages`, `questions`, `question_votes`, `polls`, `poll_options`, `poll_votes`, `resources`, `resource_downloads`.
- **Diferido**: reminders/notificaciones (Fase 8), CTAs/commerce (Fase 7), tiempo real vía Reverb (hoy polling/REST).

### Analytics (Fase 6 — `app/Domain/Analytics`, `app/Application/Analytics`, `app/Infrastructure/Analytics`)
- **Plano de analytics separado** (ADR-005): `AnalyticsCollector` (contract) + tabla append-only **versionada** `analytics_events` (`name` enum + `version` + `occurred_at` + `properties` JSON) + `DatabaseAnalyticsCollector`. Única fuente de verdad analítica, swappable a ClickHouse sin tocar callers — ADR-023.
- **Captura** en los choke points de Fase 5 (una línea por action): `registration.completed` (con **attribution** saneada), `attendance.joined`/`heartbeat`/`left`, `engagement.chat`/`question_asked`/`question_voted`/`poll_voted`/`resource_downloaded`.
- **Reporting** host (`EventAnalyticsService`): `summary` (registros, asistentes únicos, tasa de asistencia, pico de concurrencia, watch-time medio, engagement), `attendance` (concurrencia por bucket = **heatmap**, sweep line para el pico), `engagement`, `attribution` (registros/asistencia por `utm_source`). Reconstruye sesiones de presencia en PHP desde el stream join/heartbeat/leave.
- Permiso `analytics.view` (Owner/Admin/Member) + `EventPolicy::viewAnalytics`; endpoints `GET /events/{event}/analytics/{summary,attendance,engagement,attribution}`.
- Contratos de frontend: métodos `analytics*` en `@escenia/api-client` + tipos en `@escenia/types`; `attribution` opcional en el registro del `AttendeeClient`.
- Tabla: `analytics_events`.
- **Diferido**: doble escritura síncrona → async/ClickHouse; agregación en PHP → warehouse; retención de `analytics_events`; unificación con el Outbox (ADR-007).

### Commerce (Fase 7 — `app/Domain/{Commerce,Outbox}`, `app/Application/{Commerce,Outbox}`, `app/Infrastructure/Payments`)
- **Tickets** con precio en `Money` (int minor units + ISO-4217, nunca float); cupo, ventana de venta, `compare_at` para ofertas. `Order`/`OrderItem` (precio snapshoteado), `Payment`, `Cta`/`CtaClick` — ADR-024.
- **Gateways detrás de `PaymentGateway`** (nunca el SDK en el dominio): `FakePaymentGateway` (default, testeado) + adaptadores `Stripe` y `MercadoPago` (firma HMAC + intent, sin tests de integración → deuda). Selección por `PaymentGatewayFactory`/`config/payments.php`. **Credenciales cifradas** por tenant (`PaymentAccount`, `$hidden`).
- **Checkout público**: evento sin scope, tenant derivado; emite el asistente del comprador (join token una vez, `IssueAttendeeAction` compartido con el registro), crea `Order` pending + intent (llamada externa fuera de la transacción). `Order` guardado (`pending→paid→refunded`/`canceled`) con optimistic locking; `sold_count` avanza en el cobro. **Webhooks** públicos verificados por firma (`/checkout/webhooks/{account}`).
- **Outbox (ADR-007) implementado**: `outbox_events` append-only + `DispatchOutboxAction` + comando `outbox:dispatch` (agendado, at-least-once, idempotente). `order.paid` se escribe en la misma transacción; `FulfillPaidOrderHandler` registra revenue y sella `fulfilled_at`.
- **CTAs** in-event (host publica; asistente ve las live y clickea → `commerce.cta_clicked`). **Revenue analytics** (`EventCommerceReport`) leído del ledger OLTP (dinero exacto): bruto/refunded/neto, órdenes, ticket medio, por ticket, CTR de CTA.
- Permisos `commerce.view`/`commerce.manage` (`EventPolicy`); `PaymentAccount` es tenant-level. Contratos de frontend: host `ApiClient` + `AttendeeClient` (checkout público + CTAs); tipos en `@escenia/types`.
- Tablas: `tickets`, `orders`, `order_items`, `payments`, `payment_accounts`, `ctas`, `cta_clicks`, `outbox_events`.
- **Diferido**: adaptadores Stripe/MP sin integración; `createIntent` externo síncrono; posible oversell bajo concurrencia; refunds solo por webhook; códigos de descuento.

### Automation (Fase 8 — `app/Domain/{Automation,Notifications}`, `app/Application/Automation`, `app/Infrastructure/Notifications`)
- **Motor de workflows sobre el Outbox** (ADR-025): triggers (`registration.completed`, `order.paid`, `event.ended`) escritos al outbox en la misma transacción; el dispatcher enruta un topic a **varios handlers**. `HandleAutomationTrigger` construye contexto aplanado (campos públicos + ids internos `_`), evalúa condiciones y arranca un `AutomationRun` (único por automation+outbox_event, idempotente).
- **Conditional logic segura**: `ConditionEvaluator` (`{field, op, value}`, eq/ne/gt/gte/lt/lte/contains, AND, sin ejecución de código, falla cerrada) a nivel automation y por paso.
- **Pasos** detrás de `StepHandler`: `webhook` (POST saliente firmado HMAC, solo https, best-effort), `tag_contact` (CRM), `notify` (vía `Notifier`+`LogNotifier`, habilita reminders — email real futuro), `wait` (secuencias). Reanudación con `automations:resume` (agendado) para los runs en `waiting`.
- Permisos `automations.view`/`automations.manage` (tenant-level). HTTP host: CRUD + activar/desactivar + runs. Contratos de frontend: métodos `automations*`.
- Tablas: `automations`, `automation_steps`, `automation_runs`, `contact_tags`, `notifications`.
- **Diferido**: SSRF hardening de webhooks; reintentos de run fallido; envío real de notificaciones; validación por tipo de paso.

### Recording & Content (Fase 9 — `app/Domain/Content`, `app/Application/Content`, `app/Infrastructure/{Storage,Transcription}`)
- **Grabaciones** (`Recording`) desde subida local (**ticket firmado direct-to-storage**, el binario nunca pasa por Laravel) o auto-registradas desde un broadcast grabado vía **Outbox** (`broadcast.ended` → handler de Content, avanza TD-010). **ISO tracks** (`RecordingTrack`: composite/screen/camera/audio) — ADR-026.
- **Storage detrás de `RecordingStorage`** (fake por defecto + `S3` con `temporaryUploadUrl`) y **transcripción detrás de `Transcriber`** (fake + `HttpTranscriber` stub), por `config/recordings.php` (`ContentServiceProvider`). El dominio nunca ve el SDK.
- **Transcripción en Job** (`TranscribeRecordingJob`, primer uso de Jobs/Workers): endpoint `202`, el job persiste segmentos → ready/failed. **Content studio / editor**: `Clip` (rango ms, `422 invalid_clip_range`).
- Permisos `content.view`/`content.manage` (`EventPolicy`). HTTP host: recordings (list/request-upload/show/complete), transcribe, transcript, clips. Contratos de frontend: `recordings*`/`transcripts*`/`clips*`.
- Tablas: `recordings`, `recording_tracks`, `transcripts`, `transcript_segments`, `clips`.
- **Diferido**: providers reales (S3/transcriptor HTTP) sin integración; `complete` confía en el cliente; entrega del asset de egress real (webhook del provider); render/export de clips e ISO tracks; worker/Horizon en prod.

### Frontend (`apps/`, `packages/`)
- `apps/admin`: login + dashboard (tenants/workspaces), store Pinia de auth, guard de router, cliente tipado con CSRF de Sanctum y header `X-Tenant-Id`.
- `packages/types` (contratos de API), `packages/api-client`, `packages/ui` (design tokens + `AppButton`).

## Tests ejecutados

- **Backend**: 149 passed / 688 assertions (incluye aislamiento cross-tenant, máquinas de estado con optimistic locking, guest links, tokens de media, versionado de escenas, vision mixer, broadcast multistream, cifrado de stream keys, registro público + auth por token de asistente, engagement, analítica summary/attendance/heatmap/attribution, commerce checkout+webhook+Outbox+revenue, automation triggers/condiciones/secuencias/webhooks firmados, y content: subida por URL firmada + complete, transcripción en Job, clips, y auto-registro de grabación desde broadcast vía Outbox) — `php artisan test`.
- **Frontend**: 2 passed — `pnpm --filter @escenia/admin test`.
- **Static analysis**: PHPStan nivel 6 sin errores; Pint passed; vue-tsc + ESLint sin errores; build de producción OK.

## No implementar todavía

LiveKit productivo · AI · Conferences · Analytics avanzado (ClickHouse) · Qdrant · Horizon/worker productivo · Reverb · envío real de email/SMS · transcripción/storage reales · render de clips — salvo contratos/stubs estrictamente necesarios.

## Deuda técnica

Ver `docs/progress/technical-debt.md`.

## Última actualización

2026-09-08 — Fase 9 (Recording & Content) implementada y verificada en `main`.
