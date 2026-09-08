# Master Product Specification — Event Operating System

## Visión

Construir una plataforma SaaS que gestione de extremo a extremo experiencias en vivo, virtuales e híbridas.

El producto no se modela como una simple aplicación de webinars.

Se modela como un:

# Event Operating System

El núcleo debe soportar progresivamente:

- Live Studio
- Webinar
- Private Webinar
- Paid Webinar
- Evergreen Webinar
- Simulive
- Recording Studio
- Podcast
- Training
- Course
- Town Hall
- Product Launch
- Live Commerce
- Virtual Conference
- Hybrid Event
- Summit
- Expo
- Networking
- On-demand Experience

## Ciclo de vida

CREATE  
→ PRODUCE  
→ BROADCAST  
→ ENGAGE  
→ CONVERT  
→ MONETIZE  
→ ANALYZE  
→ REPURPOSE

## Referencias de producto

Tomar conceptos, no copiar interfaces:

- StreamYard: simplicidad de producción y multistream.
- Zoom Events/Webinars: producción, backstage y enterprise.
- WebinarJam: conversión y ofertas.
- Demio: intent data.
- GoTo Webinar: training y reporting.
- ClickMeeting: monetización y certificación.
- Livestorm: lifecycle B2B, API e integraciones.
- EasyWebinar: evergreen y AI funnel creation.
- MyOwnConference: educación y privacidad.
- BigMarker: conferencias, venues, sponsors e híbridos.
- YouTube Live: distribución masiva.
- Riverside: grabación local/multitrack.

## Diferenciadores propios

### AI Event Architect
Convierte un brief en un Event Blueprint estructurado.

### AI Producer
Asiste en tiempo real con producción, timing y health.

### Adaptive Audience
Permite mover asistentes entre broadcast, WebRTC, backstage y stage.

### Event Intelligence
Correlaciona engagement, contenido, leads, intención y revenue.

### Content Factory
Convierte grabaciones en transcript, capítulos, clips, posts, blog, newsletter y assets.

## Principio técnico

Laravel controla el producto.

La infraestructura de media controla transporte/composición/distribución.

No mezclar ambos planos.

## Bounded Contexts

- Accounts
- Identity
- Tenancy
- Workspaces
- Access Control
- Events
- Sessions
- Venues
- Studio
- Production
- Streaming
- Broadcasting
- Audience
- Registration
- Engagement
- Marketing
- CRM
- Commerce
- Networking
- Sponsors
- Learning
- Assessments
- Certification
- Media
- Recordings
- Content
- AI
- Automation
- Analytics
- Billing
- Usage
- Integrations
- Notifications
- Security
- Audit
- Developer Platform
- Feature Management
- System Operations

## Tipos de Event

Los tipos no deben convertirse en árboles de tablas separados.

Preferir composición por capacidades.

Ejemplo de capabilities:

- registration
- payments
- chat
- qa
- polls
- tests
- certificates
- networking
- expo
- sponsors
- recording
- multistream
- automation
- ai
- commerce
- replay
- translation
- captions
- white_label
- gamification
- breakout_rooms

## Roadmap

La implementación debe seguir `docs/roadmap/master-roadmap.md`.
