# Domain Map

## Core Domain

### Events
Fuente de verdad sobre el evento y su lifecycle.

### Studio
Participantes interactivos y control operativo del estudio.

### Production
Scenes, layouts, overlays, preview/program, run of show.

### Streaming
Orquestación de ingress/egress y destinos.

### Audience
Identidad y sesiones de espectadores.

### Engagement
Chat, Q&A, polls, reactions, resources, CTA.

## Supporting Domains

### Registration
Forms, fields, responses y acceso.

### Marketing
Attribution, campaigns, UTMs, segmentation.

### CRM
Contacts, companies, timelines e intent.

### Commerce
Products, offers, tickets, orders y payments.

### Learning
Training rules, attendance, assessments.

### Certification
Credentials y verificación.

### Recordings
Program/ISO/local tracks y processing.

### Content
Transcripts, chapters, clips y editorial assets.

### Automation
Triggers, conditions, delays y actions.

### Analytics
Event stream, aggregates e intelligence.

### Billing
Plans, entitlements, subscriptions y usage.

## Generic / Platform Domains

### Identity
Users, auth, sessions, devices.

### Tenancy
Tenants y memberships.

### Workspaces
Agrupación operacional dentro de tenant.

### Security
Rate limits, secrets, access policies.

### Audit
Trazabilidad inmutable de acciones relevantes.

### Integrations
Providers externos y webhooks.

### AI
Providers, prompts, jobs, embeddings y budgets.

## Reglas de dependencia

- Production puede consultar Studio y Events mediante Application interfaces.
- Streaming consume configuraciones de Production/Event, pero no debe decidir reglas comerciales.
- Commerce no debe depender de UI de Audience.
- Analytics recibe eventos de todos los dominios; los dominios no deben depender directamente de ClickHouse.
- AI consume interfaces de Content/Analytics/Event, no tablas de manera indiscriminada.
