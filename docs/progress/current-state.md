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

**Fase 9 — Recording & Content — COMPLETED** (2026-09-08), en `main`.

**Fase 10 — AI — COMPLETED** (2026-09-08), en `main`.

**Fase 11 — Education — COMPLETED** (2026-09-08), en `main`.

**Fase 12 — Enterprise Events — COMPLETED** (2026-09-08), en `main` (corte: agenda/sponsors/gamification; networking/venue/hybrid diferidos).

**Fase 13 — Enterprise — COMPLETED** (2026-09-09), en `main` (corte: custom domains, API keys/Developer Platform, SSO con aprovisionamiento JIT, residencia/dedicado, auditoría avanzada; SAML/OIDC real, SCIM, enforcement de residencia e infra dedicada diferidos).

**Fase 14 — Scale — COMPLETED** (2026-09-09), en `main` (seams de código con defaults network-free: captura de analítica async, extracción a warehouse y fan-out de eventos del Outbox; ClickHouse/Kafka reales, enforcement multi-región, autoscaling y madurez de DR diferidos). **Roadmap completo (Fases 0–14).**

## Stack instalado

- **Backend**: Laravel 12.69.1 sobre PHP 8.3.6; MySQL 8.3 (InnoDB, `utf8mb4`); Laravel Sanctum 4.3; Spatie Laravel Permission 8.3 (teams). Redis vía `predis` (opcional en local). **Laravel Reverb instalado y activo** como broadcaster de tiempo real (ver «Tiempo real y consola de estudio»); Horizon aún no instalado.
- **Frontend**: monorepo pnpm — Vue 3.5 + TypeScript strict + Pinia + Vue Router + Tailwind 3 + Vitest. Cuatro apps por persona — `apps/{admin,studio,attendee,speaker}` — sobre `packages/{types,api-client,ui}`.
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

### AI (Fase 10 — `app/Domain/Ai`, `app/Application/Ai`, `app/Infrastructure/Ai`)
- **AI plane detrás de contracts** (nunca el SDK en el dominio), por `config/ai.php` (`AiServiceProvider`), con fakes deterministas — ADR-027: `EmbeddingProvider` (fake bag-of-words con coseno real + HTTP stub), `AiCompletionProvider` (fake + `ClaudeCompletionProvider` Messages API, default `claude-opus-5`, stub), `VectorIndex` (`DatabaseVectorIndex` coseno en PHP + Qdrant stub).
- **Indexación por Outbox** (5º uso): `TranscribeRecordingJob` al quedar `ready` escribe `transcript.ready` → `IndexTranscriptHandler` embebe y guarda `content_chunks` (idempotente).
- **Semantic Replay** (búsqueda semántica), **Smart Q&A** (RAG con citas), **Content Factory** (`content_summaries` en `GenerateSummaryJob`: summary/chapters/highlights), **Event Architect** (plan generativo síncrono). El texto de transcript va como datos en el prompt (no instrucciones).
- RBAC reutilizado: search/ask → `content.view`, summaries → `content.manage`, architect → `events.create`. Contratos de frontend: `searchContent`/`askContent`/`requestSummary`/`designEvent`.
- Tablas: `content_chunks`, `content_summaries`.
- **Diferido**: providers reales (Claude/embeddings/Qdrant) sin integración; coseno O(n) → Qdrant a escala; chunking 1/segmento; Producer Copilot + Event Intelligence.

### Education (Fase 11 — `app/Domain/Education`, `app/Application/Education`)
- **Assessments auto-corregidos** (`Assessment`/`AssessmentQuestion`/`AssessmentSubmission`): la clave de respuestas (`correct`) **nunca sale del servidor** (Resource de asistente separado); corrección server-side (`AssessmentScorer`). Un envío por asistente (409) — ADR-028.
- **Reglas de finalización** (`CompletionRule`: minutos vistos derivados de `attendee_sessions` + aprobar el assessment). **Certificación por Outbox** (6º topic): `assessment.submitted` → `IssueCertificateHandler` evalúa elegibilidad y emite `Certificate` (idempotente); endpoint host para certificación solo por asistencia.
- **Verificación pública** `GET /certificates/verify/{code}` (sin auth, código opaco, PII mínima). RBAC `education.view`/`education.manage`. Contratos de frontend: host (`saveAssessment`/`completionRule`/`submissions`/`certificates`/`issueCertificates`) + `AttendeeClient` (`assessment`/`submitAssessment`/`verifyCertificate`).
- Tablas: `assessments`, `assessment_questions`, `assessment_submissions`, `completion_rules`, `certificates`.
- **Diferido**: render/PDF del certificado; reintentos de assessment; preguntas de texto libre; gating por entitlement.

### Enterprise Events (Fase 12 — `app/Domain/{Agenda,Sponsorship,Gamification}`, `app/Application/{Agenda,Sponsorship,Gamification}`)
- **Agenda multi-track** (`Track` + `event_sessions` extendida con `track_id`/`room`/`capacity`/`registered_count`): el asistente arma su agenda (`SessionRegistration`, único, con **cupo** → `422 session_full`, idempotente) — ADR-029.
- **Sponsors + Expo**: `Sponsor` (tier), `Booth`, `BoothLead` (visita = lead para el sponsor, idempotente). Host lee leads; asistente explora expo + visita.
- **Gamification**: `PointsAward` (ledger append-only, único por `(attendee, action, subject)`), puntos otorgados dentro de las acciones (inscribir sesión / visitar booth) + **leaderboard**. Asistente: mis puntos + ranking; host: leaderboard.
- RBAC único `enterprise.view`/`enterprise.manage`. Contratos de frontend: host (`tracks`/`sponsors`/`booths`/`leads`/`leaderboard`) + `AttendeeClient` (`agenda`/`registerForSession`/`visitBooth`/`gamification`).
- Tablas: `tracks`, `session_registrations`, `sponsors`, `booths`, `booth_leads`, `points_awards` (+ columnas de agenda en `event_sessions`).
- **Diferido**: networking (conexiones/meetings 1:1), virtual venue espacial, hybrid check-in; puntos configurables por evento.

### Enterprise (Fase 13 — `app/Domain/Enterprise`, `app/Application/Enterprise`, `app/Infrastructure/Enterprise`)
- **Nuevo contexto `Enterprise`** (tenant-owned, ULID): `CustomDomain`, `ApiKey`, `SsoConnection`; `tenants` extendida (aditivo) con `data_region` + `is_dedicated` — ADR-030.
- **Custom domains**: alta en `pending` con token de challenge, verificación detrás de `DomainVerifier` (`FakeDomainVerifier` default network-free + `DnsDomainVerifier` TXT real/stub) → `active`/`failed` (`422 domain_verification_failed`). Endpoint **público** `GET /domains/resolve?hostname=` resuelve sin scope el tenant/workspace de un dominio activo (metadata de routing, nunca secretos).
- **API keys (Developer Platform)**: patrón token-credencial (`esk_...` visible una vez, solo hash SHA-256 en DB, `$hidden`, prefijo en claro). Middleware `AuthenticateApiKey` (bearer o `X-Api-Key`) resuelve la key sin scope y establece tenant + team de Spatie desde la propia key. **Scopes** como dato (`ApiKey::SCOPES`) validados al emitir y exigidos por endpoint; muestra `GET /programmatic/events` (scope `events.read`, fuera de `auth:sanctum`). Revocación idempotente.
- **SSO**: `SsoConnection` (provider `oidc|saml`, config **cifrada** `encrypted:array`+`$hidden`, dominio de email, `default_role`, `is_active`) detrás de `IdentityProvider` (`FakeIdentityProvider` default + `OidcIdentityProvider` stub sin validación de firma). `CompleteSsoLoginAction` aprovisiona JIT: enlaza `User` por email, crea membership con `default_role` **solo en el primer login** (nunca degrada), refleja el rol en Spatie; el controlador público establece la sesión Sanctum. **`default_role` restringido a `admin|member`** (owner jamás se delega a un IdP). `401 sso_authentication_failed`.
- **Residencia + dedicado**: `GET/PUT /enterprise/settings` (Owner) lee/edita `data_region` (`us|eu|ap`) e `is_dedicated`; registra intención auditada (enforcement diferido).
- **Auditoría avanzada**: `GET /audit-logs` con filtros (action/actor/auditable_type/rango de fechas) + paginación sobre `audit_logs`, filtrada por tenant en el controlador (la tabla no está bajo el TenantScope global).
- **RBAC reutilizado** (sin permisos nuevos): `tenant.manage` (config enterprise → Owner) + `audit.view` (consulta → Owner/Admin); concern `AuthorizesTenantPermission`. Proveedores por `config/enterprise.php` (`EnterpriseServiceProvider`), fakes por defecto. Contratos de frontend: host `ApiClient` (`customDomains`/`apiKeys`/`ssoConnections`/`tenantSettings`/`auditLogs`) + tipos en `@escenia/types`.
- Tablas: `custom_domains`, `api_keys`, `sso_connections` (+ columnas `data_region`/`is_dedicated` en `tenants`).
- **Diferido (TD-035..039)**: SAML/OIDC real con validación de firma/JWKS, SCIM, enforcement de residencia e infra dedicada, emisión de TLS + edge para dominios, rate-limit/rotación por API key.

### Scale (Fase 14 — `app/Application/Analytics`, `app/Infrastructure/{Analytics,Outbox}`, `app/Providers/ScaleServiceProvider`)
- **Seams de escala por configuración, defaults network-free** (`ScaleServiceProvider`) — ADR-031; ninguna infra nueva se levanta (CLAUDE.md §3):
  1. **Captura de analítica async** (`config/analytics.php` → `capture=sync|queue`): `QueuedAnalyticsCollector` despacha `RecordAnalyticsEventJob` (en Infraestructura, usa el colector MySQL concreto en el worker para no reencolar en bucle) tras el mismo contrato `AnalyticsCollector` — desacopla el request de la escritura (avanza TD-016).
  2. **Extracción a warehouse** (`config/analytics.php` → `export=fake|clickhouse`): `AnalyticsExporter` (`FakeAnalyticsExporter` testeable + `ClickHouseAnalyticsExporter` JSONEachRow stub) + `ExtractAnalyticsAction` + comando `analytics:extract` (agendado) con high-water mark `exported_at` (idempotente) — avanza TD-017/018.
  3. **Fan-out de eventos del Outbox** (`config/outbox.php` → `stream=null|log|kafka`): `EventStreamPublisher` (`Null` default / `Log` / `Kafka` Redpanda-REST stub); `DispatchOutboxAction` publica tras los handlers (at-least-once; `ulid` = partition key). Seam de integración externa/multi-región.
- Único cambio de esquema: `exported_at` (bookkeeping write-once, como `processed_at` del Outbox) en `analytics_events`; la telemetría sigue inmutable. `AnalyticsCollector` se enlaza ahora en `ScaleServiceProvider` (movido desde `DomainServiceProvider`).
- **Diferido (TD-040..043)**: ClickHouse/warehouse real + push-down de agregación + retención; Kafka/Redpanda real + consumer groups + esquema versionado; enforcement multi-región (pinning/replicación por `data_region`); autoscaling (Horizon/HPA) y madurez de DR (backups+restore probado, RPO/RTO, runbooks). Ver `docs/architecture/scale-and-dr.md`.

### RBAC por usuario (`app/Domain/AccessControl`, `app/Application/AccessControl`)
- **Roles personalizados por tenant + permisos por usuario** sobre Spatie (teams=tenant), sin tablas nuevas — ADR-032. El catálogo de permisos sigue siendo el enum `Permission` (con `group()`/`label()` para la UI).
- **Roles de sistema** (owner/admin/member, `tenant_id` NULL) inmutables; **roles personalizados** (`tenant_id`=tenant) con CRUD y set de permisos elegido del catálogo (`CreateRoleAction`/`UpdateRoleAction`/`DeleteRoleAction`).
- **Tres niveles por miembro**: rol base autoritativo (`ChangeMembershipRoleAction`, espeja `TenantMembership.role` ↔ rol de sistema Spatie), roles personalizados aditivos (`SyncMemberRolesAction`) y permisos directos aditivos (`SyncMemberPermissionsAction`). Acceso efectivo = unión.
- **Guardas anti-escalada** (`AccessGuard`): solo se conceden permisos que el actor posee (`403 privilege_escalation`); tocar el tier owner exige ser owner; no se degrada al último owner; roles de sistema no se gestionan como personalizados (`422 system_role`).
- Superficie host `members.manage`: `GET /permissions` (catálogo agrupado), `roles` CRUD, `GET /members`, `GET /members/{user}`, `PUT /members/{user}/{membership-role,roles,permissions}`. Mutaciones auditadas (`rbac.*`). Contratos de frontend: `permissionCatalog`/`roles`/`createRole`/`updateRole`/`deleteRole`/`members`/`memberAccess`/`changeMembershipRole`/`syncMemberRoles`/`syncMemberPermissions`.
- **Diferido (TD-044)**: permisos negativos/denegación por usuario, jerarquía de roles, invitación/alta-baja de miembros.

### Configuración en caliente / Settings (`app/Domain/Settings`, `app/Application/Settings`, `app/Infrastructure/Settings`)
- **Todo configurable desde el sistema** — cualquier API externa o dato del catálogo se carga desde la app, no solo por `env` — ADR-033. **Catálogo como dato** (`SettingCatalog`): cada clave declara scope (system|tenant|both), tipo, si es secreta, y la `configKey` de la que hereda su default (acota y valida "todo configurable").
- **Store cifrado** (`settings`: scope/tenant_id?/key/value cifrado JSON; no usa TenantScope global). **Resolver con precedencia tenant→sistema→config/env** (`Settings`/`SettingsRepository`), memoizado por request y **defensivo** (cae al default si el store no está disponible).
- **Dos actores**: **super-admin de plataforma** (nueva bandera `users.is_super_admin`, concedida por `escenia:super-admin`, nunca mass-assignable ni por API de tenant; middleware `super.admin`) edita `GET/PUT /system/settings`; el **Owner** (`tenant.manage`) edita sus overrides en `GET/PUT /settings`. **Secretos write-only** (nunca se devuelven; `is_set` + máscara; jamás al audit).
- **Rewire de proveedores**: `AiServiceProvider`/`ScaleServiceProvider`/`EnterpriseServiceProvider` (+ `domain_target`) resuelven selección/credenciales vía `Settings` con **fallback a `config`** (comportamiento idéntico con el store vacío). Contratos de frontend: `systemSettings`/`updateSystemSettings`/`tenantConfig`/`updateTenantConfig`.
- Tablas: `settings` (+ columna `is_super_admin` en `users`). **Diferido (TD-045)**: binding per-tenant en caliente de proveedores singleton, rotación/versionado de secretos, caché distribuida de settings.

### Tiempo real y consola de estudio (post-roadmap, 2026-09-13)
- **Reverb activado (verificado E2E)**: instalado `laravel/reverb` + `config/reverb.php`; los eventos de engagement (chat/preguntas/encuestas) que ya implementaban `ShouldBroadcast` sobre el canal público `event.{ulid}` ahora tienen un servidor WebSocket real detrás. Activación por `.env` (gitignored): `BROADCAST_CONNECTION=reverb` + `REVERB_*` (documentados en `.env.example`) y `VITE_REVERB_*` por app; **sin esas claves el frontend sigue en su baseline de polling** (no-op), así que el cambio es aditivo y seguro por defecto. Verificado localmente extremo a extremo: `chat POST → ChatMessagePosted (encolado) → worker → Reverb :8080 → frame WebSocket → navegador` (una sonda WS cruda capturó el payload, descartando el polling como vía de entrega).
- **Tiempo real en la consola de estudio (canal privado)**: el productor ve entrar/moverse/salir participantes en vivo. Como el backstage es información de producción (no pública como el chat), va por un **canal privado** `studio.{ulid}` autorizado (`routes/channels.php`, permiso `StudioManage` en el tenant *del evento*, team de Spatie fijado como en `ResolveTenant`); la auth de broadcasting (`/broadcasting/auth`, `withRouting(channels:)`) queda montada y admin la reutilizará. Backend: `StudioParticipantActivity` (broadcastAs `participant.activity`) emitido en admit/move/guest-join; frontend `useStudioRealtime` (Echo privado, auth sobre el origen SPA con cookie Sanctum + `X-XSRF-TOKEN` vía proxy `/broadcasting`) y la consola **refetchea** al recibir. Verificado E2E en vivo: auth `200`, sonda WS capturó el frame y el tile se actualizó sin recargar.
- **Chat en vivo en la consola**: además del canal privado, la consola consume el canal PÚBLICO `event.{ulid}` (`useEventChannel` en studio) para el chat del evento — carga histórico (`api.eventChat`), añade mensajes por WS (dedupe + autoscroll) y el productor publica como host (`api.postEventChat`, distintivo «host»). Verificado E2E: un mensaje de asistente posteado por fuera apareció en la consola por WS, y el host publicó con badge sin duplicado.
- **Presencia (nº de asistentes en línea)**: canal de **presencia** `presence-viewers.{ulid}` (Reverb) — conteo exacto y auto-corregido (los miembros caen al desconectar el WS). Los asistentes se unen (rol `attendee`) autorizados por un endpoint de token propio (`POST /attend/broadcasting/auth`) que firma el payload de presencia (protocolo Pusher: `key:HMAC-SHA256(socket:channel:channel_data, secret)`); la consola se une por Sanctum (callback en `channels.php`, rol `host`, no cuenta) y muestra «N en línea», y el asistente ve un badge. `useViewerCount` en ambas apps. **Fix**: attendee/speaker salen de `SANCTUM_STATEFUL_DOMAINS` (son apps de token; marcarlas stateful forzaba CSRF 419). Verificado E2E: consola «1 en línea» con un asistente, «0» al cerrar su pestaña.
- **Admin: inscripciones en vivo**: broadcast ligero `AttendeeRegistered` (solo el total, **sin PII**) en el canal público `event.{ulid}` al registrarse un asistente nuevo (`wasRecentlyCreated`); la vista de Registro (`useEventChannel` en admin) refetchea la lista al recibirlo — los nombres/correos viajan solo por la API autenticada — y muestra un indicador «● en vivo». Verificado E2E: el contador pasó de 2 → 3 al registrar por fuera, con el nuevo inscrito apareciendo en la lista. Con esto, el tiempo real cubre las tres apps (asistente/studio/admin).

### Networking (post-roadmap, cierra el sub-módulo diferido de Enterprise Events — TD-033)
- **Nuevo contexto `Networking`** (`app/Domain/Networking`, `app/Application/Networking`): dos agregados con máquina de estados guardada, como el resto del sistema:
  - `Connection` (pending → accepted|declined): un asistente solicita, el otro responde. Único por `(evento, requester, addressee)`; rechaza auto-conexión (422) y duplicados en cualquier dirección (409); una solicitud rechazada se puede reabrir.
  - `Meeting` (proposed → accepted|declined|canceled; accepted → canceled): 1:1 con hora, duración y tema; el invitado responde, cualquiera cancela (registra `canceled_by`).
- **Superficie de asistente** `/attend/networking/*` (token, aislada por evento y por attendee — solo las partes implicadas actúan, resuelto por scope → 404): directorio de personas con mi estado de conexión, conexiones (listar/solicitar/aceptar/rechazar) y reuniones (listar/proponer/aceptar/rechazar/cancelar). Transiciones guardadas con CAS; excepciones mapeadas (422/409). Tablas: `connections`, `meetings`.
- **Frontend**: pestaña «Personas» en el hub del asistente (`NetworkingPanel`) con secciones Personas/Conexiones/Reuniones; tipos + métodos en los packages compartidos.
- **Verificado**: 5 tests (conexión pedir/aceptar, auto-conexión, duplicado, solo-el-addressee-responde; reunión proponer/aceptar/cancelar + transición inválida; directorio) + PHPStan L6/Pint; E2E en vivo con dos asistentes reales (Marta↔Carlos: conectar → «Conectado»; proponer reunión → aceptar → «Confirmada»).
- **Diferido**: opt-in del directorio (hoy los nombres son visibles entre asistentes), notificaciones/tiempo real de solicitudes, matchmaking. Ver TD-033.

### Commerce: reembolsos + cupones (post-roadmap, TD-022)
- **Reembolsos desde el host**: el organizador reembolsa un pedido pagado desde admin (antes solo por webhook del gateway). `PaymentGateway::refund()` (fake no-op + stubs Stripe/MP); la transición guardada `paid→refunded` (CAS + libera stock + marca el pago, idempotente) se extrajo a `RefundOrderAction`, reutilizada por el webhook y por el host (`IssueRefundAction`, que verifica el pedido antes de llamar al gateway). Endpoint `POST /events/{event}/commerce/orders/{order}/refund` (commerce.manage); botón «Reembolsar» en admin. El reporte ahora define gross = recaudado (paid+refunded) y net = gross − refunded (0 tras reembolso total).
- **Cupones de descuento**: nuevo agregado `Coupon` (percent|fixed, código único por evento en mayúsculas, ventana + tope de usos opcionales; `isRedeemable()`/`discountFor()` que nunca excede el subtotal). El checkout acepta `coupon_code`, fija `discount_minor`+`coupon_id` y el total con descuento; el conteo de usos avanza al **pagar** (no al iniciar el checkout). CRUD de host `/commerce/coupons` (listar/crear/desactivar-suave); panel «Cupones» en admin. Migración: `coupons` + `discount_minor`/`coupon_id` en `orders`.
- **Verificado**: 22 tests de Commerce (incl. refund libera stock + reporte + idempotente; cupón percent contado al pagar, fijo topado, desconocido/desactivado/duplicado/agotado) + Pint/PHPStan L6; **E2E en vivo**: cupón VERANO25 creado en admin (usos reflejados), pedido con descuento (75 vs 100 MXN) reembolsado desde el botón → «Reembolsada», neto 0, stock liberado.
- **Diferido (TD-022)**: reembolso parcial/por ítem; cupones a nivel tenant/globales; endurecer el tope de usos bajo concurrencia; campo de cupón en una futura página de checkout del comprador.

### Education: PDF del certificado (post-roadmap, TD-031)
- **Certificado descargable en PDF** (antes solo datos + verificación por código). `CertificateRenderer` (contrato en Domain) + `DompdfCertificateRenderer` (infra, `barryvdh/laravel-dompdf` detrás del contrato — el SDK nunca entra al dominio), bindeado en `DomainServiceProvider`. Plantilla Blade dompdf-safe (A4 horizontal, marca navy + acento del `BrandKit` del workspace si existe, nombre/evento/fecha `es`/código).
- `RenderCertificatePdfAction` arma un VO `CertificateData` (sin filtrar el modelo al renderer) y resuelve el BrandKit una vez (color + logo). Endpoint **público** `GET /certificates/{code}/pdf` (resuelto sin scope por el código opaco = credencial compartible, throttled, `Content-Disposition: attachment`), render **on-demand**. Frontend: botón «Descargar certificado (PDF)» en la vista de verificación del asistente.
- **Logo + QR**: la plantilla lleva un **lockup de marca** (badge navy con borde de acento + wordmark, o el logo del BrandKit si su token `logo` es un data URI — nunca por URL remota, offline + sin SSRF) y un **QR** a la URL de verificación (`{frontend_url|app_url}/verificar/{code}`). El QR va detrás de un contrato `QrCodeGenerator` (`app/Domain/Shared/Contracts`) + `EndroidQrCodeGenerator` (`endroid/qr-code ^6`, PNG con GD, offline), inyectado en el renderer.
- **Verificado**: 3 tests (PDF `%PDF` por código; 404 desconocido; QR = PNG data URI) + Pint/PHPStan L6; E2E en vivo (descarga real del servidor → 200 `application/pdf` con QR embebido).
- **Diferido (TD-031)**: pre-generar en Job + guardar en object storage para volumen; pipeline de subida de logo del BrandKit (a data URI/storage) + tipografía de marca.
- **Vídeo de participantes en la consola de estudio (LiveKit)**: el productor no es un `StudioParticipant`, así que se añadió `IssueHostTokenAction` + `POST /events/{event}/studio/host-token` (identidad `host-{id}`, grants `full` sobre la sesión viva; `409` si el studio no está en vivo). El dominio no toca el SDK — identidad/grants se resuelven en la Action y se entregan al `MediaProviderContract`. `StudioParticipantResource` expone `identity` para casar las pistas remotas con cada tile. En el frontend, `useStudioRoom` conecta como host, se suscribe a las pistas de vídeo y las pinta por identidad; `livekit-client` se importa dinámico (chunk aparte) y **solo conecta si la URL es real** (`esUrlDeMedios`), de modo que dev/`fake` muestra iniciales. Verificado por tests (incl. decodificar un JWT LiveKit real con grants de host, sin servidor) y sin regresión contra el proveedor `fake`. **Pendiente**: E2E con un servidor LiveKit real (bloqueado en local por Docker Desktop sin distro WSL; es infra del entorno, no código).
- **Apps de asistente, ponente y studio**: además de `apps/admin`, se construyeron `apps/attendee` (registro público + hub en vivo con chat/Q&A/encuestas/agenda/expo/recursos/evaluación/ranking, auth por token), `apps/speaker` (canje de guest-link + sala con chequeo de dispositivo y `useLiveKit`), y `apps/studio` (consola de productor: mixer preview/program, escenas, tiles de participantes con ciclo de vida guardado, emisión). Tiempo real de asistente vía `useEventChannel` (config-gated por `VITE_REVERB_APP_KEY`).

### Frontend (`apps/`, `packages/`)
- `apps/admin`: login + `AdminLayout` (shell con navegación por permisos + switcher de tenant), store Pinia de auth (tenant activo + gates `isSuperAdmin`/`canManageMembers`/`canManageTenant`), guard de router (incl. super-admin), cliente tipado con CSRF de Sanctum y header `X-Tenant-Id`. **Superficies de administración**: Overview (tenants/workspaces); **Members & roles** (`members.manage`: miembros + editor de acceso por usuario rol-base/roles/permisos/efectivos + CRUD de roles con `PermissionPicker`); **Tenant settings** (`tenant.manage`) y **System settings** (super-admin) con `SettingsForm` generado del catálogo (controles por tipo, secretos write-only).
- `packages/types` (contratos de API), `packages/api-client`, `packages/ui` (design tokens + `AppButton`).

## Tests ejecutados

- **Backend**: 206 passed / 973 assertions (incluye aislamiento cross-tenant, máquinas de estado con optimistic locking, media/escenas/mixer/broadcast+cifrado, auth por token de asistente, engagement, analítica, commerce checkout+webhook+Outbox+revenue, automation triggers/secuencias/webhooks firmados, content subida-firmada+transcripción-en-Job+clips, AI indexación+búsqueda-semántica-real+RAG+resúmenes, education corrección-server-side+certificación-por-Outbox+verificación-pública, enterprise events: agenda multi-track con cupo/leads/gamification, enterprise: dominios custom verificados/API keys con scopes/SSO con aprovisionamiento JIT/residencia/auditoría avanzada, scale: extracción de analítica con high-water mark/captura async por cola/fan-out del Outbox al stream, RBAC por usuario: roles personalizados/tier base/roles/permisos aditivos con guardas anti-escalada, y settings: config de sistema por super-admin/override por tenant con precedencia/secretos write-only cifrados/rewire de proveedores) — `php artisan test`.
- **Frontend**: 2 passed — `pnpm --filter @escenia/admin test`.
- **Static analysis**: PHPStan nivel 6 sin errores; Pint passed; vue-tsc + ESLint sin errores; build de producción OK.

## No implementar todavía

LiveKit productivo (servidor de medios real + egress; el token de host y los tiles de vídeo ya están cableados, ver «Tiempo real y consola de estudio») · networking/virtual-venue/hybrid (resto de Enterprise Events) · SSO real (SAML/OIDC con validación de firma) · SCIM · enforcement de residencia + infra dedicada · TLS/edge para dominios custom · ClickHouse/warehouse real · Kafka/Redpanda real + consumer groups · multi-region (pinning/replicación) · autoscaling (Horizon/HPA) + madurez de DR · Qdrant productivo · envío real de email/SMS · transcripción/storage/LLM reales · render de clips/certificados — salvo contratos/stubs estrictamente necesarios. **Los seams de código ya existen** (ADR-031 y anteriores); solo falta activar/integrar la infra por config. **Reverb ya está activado** (broadcaster de tiempo real, verificado E2E).

## Deuda técnica

Ver `docs/progress/technical-debt.md`.

## Última actualización

2026-09-09 — Fase 14 (Scale) implementada y verificada en `main`. **Roadmap completo (Fases 0–14).**

2026-09-13 — Post-roadmap en `main`: construidas las apps `studio`/`attendee`/`speaker` (además de `admin`); **Reverb activado y verificado E2E** como tiempo real; **consola de estudio con token de host + tiles de vídeo LiveKit** (verificado por tests y contra el proveedor `fake`; E2E con servidor LiveKit real pendiente — bloqueado en local por Docker/WSL, es infra del entorno).
