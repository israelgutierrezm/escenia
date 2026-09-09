/**
 * Shared API contract types for Escenia. These mirror the backend API Resources
 * (`app/Http/Resources`) and are the single source of truth for the SPA apps.
 * Public identifiers are always the opaque ULID string — never the internal id.
 */

export interface ApiResource<T> {
  data: T
}

export interface ApiCollection<T> {
  data: T[]
  links?: PaginationLinks
  meta?: PaginationMeta
}

export interface PaginationLinks {
  first: string | null
  last: string | null
  prev: string | null
  next: string | null
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface ApiErrorPayload {
  message: string
  error_code: string
  request_id: string
  errors?: Record<string, string[]>
}

export type TenantStatus = 'active' | 'suspended'
export type TenantRole = 'owner' | 'admin' | 'member'
export type MembershipStatus = 'invited' | 'active' | 'suspended'

export interface Tenant {
  id: string
  name: string
  slug: string
  status: TenantStatus
  role?: TenantRole | null
  membership_status?: MembershipStatus | null
  created_at: string | null
}

export interface User {
  id: string
  name: string
  email: string
  timezone: string | null
  locale: string | null
  tenants?: Tenant[]
  created_at: string | null
}

export interface Workspace {
  id: string
  name: string
  slug: string
  status: string
  created_at: string | null
}

// ---- Events (Fase 1 — Event Core) ----

export type EventStatus = 'draft' | 'scheduled' | 'live' | 'ended' | 'archived' | 'canceled'

export type EventTypeKey =
  | 'webinar'
  | 'live_studio'
  | 'evergreen'
  | 'simulive'
  | 'training'
  | 'course'
  | 'town_hall'
  | 'product_launch'
  | 'virtual_conference'
  | 'hybrid_event'
  | 'podcast'
  | 'on_demand'

export type CapabilityKey =
  | 'registration'
  | 'payments'
  | 'chat'
  | 'qa'
  | 'polls'
  | 'tests'
  | 'certificates'
  | 'networking'
  | 'expo'
  | 'sponsors'
  | 'recording'
  | 'multistream'
  | 'automation'
  | 'ai'
  | 'commerce'
  | 'replay'
  | 'translation'
  | 'captions'
  | 'white_label'
  | 'gamification'
  | 'breakout_rooms'

export type SpeakerRole = 'host' | 'speaker' | 'moderator' | 'panelist'
export type SessionStatus = 'scheduled' | 'live' | 'ended' | 'canceled'

export interface EventCapability {
  id: string
  capability: CapabilityKey
  enabled: boolean
  settings: Record<string, unknown> | null
}

export interface EventSession {
  id: string
  title: string
  status: SessionStatus
  scheduled_start_at: string | null
  scheduled_end_at: string | null
  position: number
}

export interface EventSpeaker {
  id: string
  name: string
  email: string | null
  headline: string | null
  bio: string | null
  avatar_url: string | null
  role: SpeakerRole
  position: number
}

export interface EventScheduleItem {
  id: string
  title: string
  description: string | null
  starts_at: string | null
  ends_at: string | null
  position: number
}

export interface EventTemplate {
  id: string
  name: string
  type: EventTypeKey
  description: string | null
  default_capabilities: CapabilityKey[]
  is_system: boolean
}

/** Named EventModel to avoid clashing with the DOM `Event` global. */
export interface EventModel {
  id: string
  type: EventTypeKey
  status: EventStatus
  title: string
  slug: string
  description: string | null
  timezone: string
  scheduled_start_at: string | null
  scheduled_end_at: string | null
  actual_start_at: string | null
  actual_end_at: string | null
  allowed_transitions: EventStatus[]
  workspace?: Workspace
  capabilities?: EventCapability[]
  sessions?: EventSession[]
  speakers?: EventSpeaker[]
  schedule?: EventScheduleItem[]
  created_at: string | null
}

// ---- Studio (Fase 2 — Studio MVP) ----

export type StudioStatus = 'idle' | 'live' | 'ended'
export type StudioSessionStatus = 'live' | 'ended'
export type ParticipantRole = 'host' | 'producer' | 'speaker' | 'guest'
export type ParticipantStage = 'invited' | 'green_room' | 'backstage' | 'stage' | 'left'

export interface StudioSession {
  id: string
  status: StudioSessionStatus
  provider: string
  room: string
  started_at: string | null
  ended_at: string | null
}

export interface Studio {
  id: string
  name: string
  status: StudioStatus
  provider: string
  current_session: StudioSession | null
  /** Present on mixer responses (preview/take). */
  preview_scene?: string | null
  program_scene?: string | null
}

export interface StudioParticipant {
  id: string
  name: string
  role: ParticipantRole
  stage: ParticipantStage
  device_checked: boolean
  joined_at: string | null
  left_at: string | null
}

export interface StudioGuestLink {
  id: string
  name: string
  role: ParticipantRole
  expires_at: string | null
  single_use: boolean
  max_uses: number | null
  uses: number
  revoked_at: string | null
}

/** Short-lived media credential for the client SDK to join the room. */
export interface AccessTokenPayload {
  token: string
  url: string
  identity: string
  room: string
  expires_at: string
}

export interface AdmitParticipantResult {
  participant: StudioParticipant
  access: AccessTokenPayload
}

export interface GuestJoinResult {
  participant: StudioParticipant
  session: StudioSession
  access: AccessTokenPayload
}

export interface GuestLinkCreated {
  link: StudioGuestLink
  token: string
  join_url: string
}

// ---- Production Engine (Fase 3) ----

export interface SceneVersion {
  id: string
  version: number
  schema_version: string
  definition: Record<string, unknown>
  is_current: boolean
  created_at: string | null
}

export interface Scene {
  id: string
  name: string
  position: number
  current_version: SceneVersion | null
}

export interface BrandKit {
  id: string
  name: string
  tokens: Record<string, unknown> | null
  is_default: boolean
}

export interface RunOfShowItem {
  id: string
  title: string
  notes: string | null
  duration_seconds: number | null
  position: number
  /** Scene ULID, present when the relation is loaded. */
  scene?: string
}

// ---- Broadcast (Fase 4) ----

export type BroadcastStatus = 'idle' | 'starting' | 'live' | 'ended' | 'failed'
export type BroadcastHealth = 'unknown' | 'healthy' | 'degraded' | 'failed'
export type DestinationProtocol = 'rtmp' | 'rtmps' | 'srt'
export type BroadcastDestinationStatus = 'pending' | 'live' | 'failed'

/** The stream key is a secret and is never returned by the API. */
export interface StreamDestination {
  id: string
  name: string
  protocol: DestinationProtocol
  url: string
  is_enabled: boolean
}

export interface BroadcastDestination {
  id: string
  status: BroadcastDestinationStatus
  /** StreamDestination ULID, present when the relation is loaded. */
  destination?: string
}

export interface BroadcastSession {
  id: string
  status: BroadcastStatus
  health: BroadcastHealth
  record: boolean
  started_at: string | null
  ended_at: string | null
  destinations?: BroadcastDestination[]
}

// ---- Registration & Engagement (Fase 5 — Webinar) ----

export type FieldType = 'text' | 'email' | 'textarea' | 'select' | 'checkbox'

export interface RegistrationField {
  key: string
  label: string
  type: FieldType
  required?: boolean
  options?: string[]
}

export interface RegistrationForm {
  id: string
  is_open: boolean
  fields: RegistrationField[]
}

/** The public registration surface for an event (form + minimal event info). */
export interface PublicRegistration {
  event: { id: string; title: string }
  form: RegistrationForm
}

export interface Attendee {
  id: string
  name: string
  email: string | null
}

export interface AttendeeRegistered {
  attendee: Attendee
  /**
   * The join token — the attendee's credential for every attendee-facing
   * endpoint. Returned exactly once at registration; persist it client-side.
   */
  token: string
}

/** Host view of a registrant. */
export interface Registrant {
  id: string
  answers: Record<string, unknown>
  registered_at: string | null
  contact: { name?: string; email?: string }
}

export interface AttendeeSession {
  id: string
  joined_at: string | null
  left_at: string | null
  last_seen_at: string | null
}

export interface ChatMessage {
  id: string
  author_name: string
  is_host: boolean
  body: string
  created_at: string | null
}

export type QuestionStatus = 'open' | 'answered'

export interface Question {
  id: string
  author_name: string
  body: string
  status: QuestionStatus
  answer: string | null
  votes_count: number
  answered_at: string | null
  created_at: string | null
}

export type PollStatus = 'draft' | 'open' | 'closed'

export interface PollOption {
  id: string
  label: string
  position: number
  votes_count: number
}

export interface Poll {
  id: string
  question: string
  status: PollStatus
  options?: PollOption[]
  created_at: string | null
}

/** A downloadable handout attached to an event. */
export interface EventResource {
  id: string
  title: string
  url: string
  position: number
  downloads_count: number
}

// ---- Analytics (Fase 6) ----

/** Optional acquisition context captured at registration (never in the URL). */
export interface Attribution {
  utm_source?: string
  utm_medium?: string
  utm_campaign?: string
  utm_term?: string
  utm_content?: string
  referrer?: string
  landing_path?: string
}

export interface EngagementTotals {
  chat_messages: number
  questions_asked: number
  question_votes: number
  poll_votes: number
  resource_downloads: number
}

export interface AnalyticsSummary {
  registrations: number
  registered_attendees: number
  attended_attendees: number
  /** attended / registered, 0..1. */
  attendance_rate: number
  peak_concurrent: number
  avg_watch_minutes: number
  engagement: EngagementTotals
}

export interface AttendancePoint {
  /** Bucket start, ISO-8601. */
  t: string
  concurrent: number
}

export interface AttendanceTimeline {
  interval_seconds: number
  points: AttendancePoint[]
}

export interface EngagementReport {
  totals: EngagementTotals
}

export interface AttributionSource {
  source: string
  registrations: number
  attended: number
}

export interface AttributionReport {
  sources: AttributionSource[]
}

// ---- Commerce (Fase 7) ----

export type PaymentGatewayName = 'fake' | 'stripe' | 'mercadopago'
export type OrderStatus = 'pending' | 'paid' | 'canceled' | 'refunded'

/** Money as integer minor units + ISO-4217 currency. Never a float. */
export interface Money {
  minor_units: number
  currency: string
}

export interface Ticket {
  id: string
  name: string
  description: string | null
  price: Money
  compare_at: Money | null
  capacity: number | null
  remaining: number | null
  on_sale: boolean
  position: number
}

export interface OrderItem {
  id: string
  ticket_name: string
  quantity: number
  subtotal: Money
}

export interface Order {
  id: string
  status: OrderStatus
  buyer_name: string
  buyer_email: string
  total: Money
  gateway: string
  paid_at: string | null
  created_at: string | null
  items?: OrderItem[]
}

/** Returned once at checkout — how the client completes payment. */
export interface PaymentIntent {
  reference: string
  status: string
  client_secret: string | null
  redirect_url: string | null
}

export interface CheckoutResult {
  order: Order
  attendee: Attendee
  /** The buyer's attendee join token, returned once. */
  token: string
  payment: PaymentIntent
}

export interface Cta {
  id: string
  title: string
  body: string | null
  url: string | null
  ticket?: string
  is_active: boolean
  live: boolean
  clicks_count: number
  position: number
}

export interface PaymentAccount {
  id: string
  gateway: PaymentGatewayName
  display_name: string
  currency: string
  is_active: boolean
  webhook_url: string
}

export interface RevenueByTicket {
  name: string
  units: number
  revenue_minor: number
}

export interface RevenueReport {
  currency: string
  gross_minor: number
  refunded_minor: number
  net_minor: number
  orders_paid: number
  avg_order_minor: number
  by_ticket: RevenueByTicket[]
  cta: { clicks: number; unique_clickers: number }
}

// ---- Automation (Fase 8) ----

export type TriggerEvent = 'registration.completed' | 'order.paid' | 'event.ended'
export type AutomationStepType = 'webhook' | 'tag_contact' | 'notify' | 'wait'
export type AutomationRunStatus = 'running' | 'waiting' | 'completed' | 'failed'
export type ConditionOperator = 'eq' | 'ne' | 'gt' | 'gte' | 'lt' | 'lte' | 'contains'

export interface Condition {
  field: string
  op: ConditionOperator
  value: unknown
}

export interface AutomationStep {
  id: string
  position: number
  type: AutomationStepType
  config: Record<string, unknown> | null
  conditions: Condition[] | null
}

export interface Automation {
  id: string
  name: string
  trigger: TriggerEvent
  is_active: boolean
  conditions: Condition[] | null
  steps?: AutomationStep[]
}

export interface AutomationRun {
  id: string
  trigger: TriggerEvent
  status: AutomationRunStatus
  current_position: number
  resume_at: string | null
  log: Array<{ position: number; type: string; summary: string }> | null
  completed_at: string | null
  created_at: string | null
}

/** A step in a create-automation request. */
export interface AutomationStepInput {
  type: AutomationStepType
  config?: Record<string, unknown>
  conditions?: Condition[]
}

// ---- Content: Recording & Content (Fase 9) ----

export type RecordingSource = 'broadcast' | 'upload'
export type RecordingStatus = 'pending' | 'processing' | 'ready' | 'failed'
export type TrackKind = 'composite' | 'screen' | 'camera' | 'audio'
export type TranscriptStatus = 'pending' | 'processing' | 'ready' | 'failed'
export type ClipStatus = 'draft' | 'ready'

export interface RecordingTrack {
  id: string
  kind: TrackKind
  label: string | null
  status: RecordingStatus
  size_bytes: number | null
}

export interface TranscriptSegment {
  start_ms: number
  end_ms: number
  speaker: string | null
  text: string
}

export interface Transcript {
  id: string
  provider: string
  language: string
  status: TranscriptStatus
  segments?: TranscriptSegment[]
}

export interface Clip {
  id: string
  title: string
  start_ms: number
  end_ms: number
  status: ClipStatus
  created_at: string | null
}

export interface Recording {
  id: string
  source: RecordingSource
  status: RecordingStatus
  title: string | null
  duration_ms: number | null
  size_bytes: number | null
  format: string | null
  playback_url: string | null
  tracks?: RecordingTrack[]
  transcripts?: Transcript[]
  clips?: Clip[]
  created_at: string | null
}

/** A signed direct-to-storage upload ticket. Upload the binary to `url`; never through the API. */
export interface UploadTicket {
  url: string
  method: string
  headers: Record<string, string>
  key: string
  expires_at: string
}

export interface RecordingUpload {
  recording: Recording
  upload: UploadTicket
}

// ---- AI (Fase 10) ----

export type SummaryKind = 'summary' | 'chapters' | 'highlights'
export type SummaryStatus = 'pending' | 'processing' | 'ready' | 'failed'

/** One semantic-search hit: a transcript chunk with its relevance score. */
export interface SearchHit {
  id: string
  recording: string | null
  start_ms: number
  end_ms: number
  text: string
  score: number
}

/** A cited transcript excerpt supporting a Smart Q&A answer. */
export interface Citation {
  id: string
  start_ms: number
  end_ms: number
  text: string
}

export interface QaAnswer {
  answer: string
  model: string
  citations: Citation[]
}

export interface ContentSummary {
  id: string
  kind: SummaryKind
  status: SummaryStatus
  provider: string | null
  model: string | null
  content: string | null
  created_at: string | null
}

export interface EventPlan {
  plan: string
  model: string
}

// ---- Education (Fase 11) ----

export type AssessmentQuestionType = 'single_choice' | 'multiple_choice' | 'true_false'

export interface AssessmentOption {
  key: string
  label: string
  /** Present only in the host view; never sent to attendees. */
  correct?: boolean
}

export interface AssessmentQuestion {
  id: string
  prompt: string
  type: AssessmentQuestionType
  points?: number
  options: AssessmentOption[]
}

export interface Assessment {
  id: string
  title: string
  passing_score: number
  is_published?: boolean
  questions: AssessmentQuestion[]
}

export interface AssessmentSubmission {
  id: string
  score: number
  passed: boolean
  submitted_at: string
  attendee?: { name?: string; email?: string }
}

export interface CompletionRule {
  min_watch_seconds: number | null
  require_assessment: boolean
}

export interface Certificate {
  id: string
  code: string
  recipient_name: string
  issued_at: string
  verify_url: string
}

export interface CertificateVerification {
  valid: boolean
  code?: string
  recipient_name?: string
  event_title?: string | null
  issued_at?: string
}

// ---- Enterprise Events (Fase 12) ----

export type SponsorTier = 'platinum' | 'gold' | 'silver' | 'bronze' | 'community'

export interface Track {
  id: string
  name: string
  color: string | null
  position: number
}

export interface AgendaSession {
  id: string
  title: string
  room: string | null
  capacity: number | null
  registered_count: number
  has_capacity: boolean
  track?: string
  starts_at: string | null
  ends_at: string | null
}

export interface Sponsor {
  id: string
  name: string
  tier: SponsorTier
  logo_url: string | null
  website_url: string | null
  position: number
}

export interface Booth {
  id: string
  name: string
  description: string | null
  url: string | null
  leads_count: number
  sponsor?: { id: string; name: string; tier: SponsorTier }
}

export interface BoothLead {
  id: string
  note: string | null
  created_at: string | null
  booth?: string
  attendee?: { name?: string; email?: string }
}

export interface LeaderboardEntry {
  attendee: string
  name: string
  points: number
}

export interface AttendeeGamification {
  points: number
  leaderboard: LeaderboardEntry[]
}

// ---- Enterprise (Fase 13) ----

export type DomainStatus = 'pending' | 'active' | 'failed'

export interface DnsChallenge {
  type: string
  name: string
  value: string
}

export interface CustomDomain {
  id: string
  hostname: string
  status: DomainStatus
  target: string | null
  dns_challenge: DnsChallenge
  workspace_id?: string | null
  verified_at: string | null
  created_at: string | null
}

export type ApiKeyScope = 'events.read' | 'events.write' | 'analytics.read'

export interface ApiKey {
  id: string
  name: string
  prefix: string
  scopes: ApiKeyScope[]
  last_used_at: string | null
  expires_at: string | null
  revoked_at: string | null
  created_at: string | null
}

export interface IssuedApiKey {
  /** The raw key — shown exactly once, never returned again. */
  token: string
  key: ApiKey
}

export type SsoProvider = 'oidc' | 'saml'

export interface SsoConnection {
  id: string
  provider: SsoProvider
  display_name: string
  domain: string | null
  default_role: 'admin' | 'member'
  is_active: boolean
  created_at: string | null
}

export type DataRegion = 'us' | 'eu' | 'ap'

export interface TenantSettings {
  id: string
  name: string
  data_region: DataRegion
  is_dedicated: boolean
}

export interface AuditLogEntry {
  id: string
  action: string
  actor_label: string | null
  auditable_type: string | null
  context: Record<string, unknown> | null
  ip_address: string | null
  request_id: string | null
  created_at: string | null
}

// ---- Access control: roles & per-user permissions ----

export type TenantRoleKey = 'owner' | 'admin' | 'member'

export interface RoleModel {
  name: string
  is_system: boolean
  permissions: string[]
}

export interface PermissionGroup {
  group: string
  permissions: Array<{ value: string; label: string }>
}

export interface MemberSummary {
  id: string
  name: string
  email: string
  membership_role: TenantRoleKey
  status: string
}

export interface MemberAccess {
  id: string
  name: string
  email: string
  membership_role: TenantRoleKey | null
  custom_roles: string[]
  direct_permissions: string[]
  effective_permissions: string[]
}

// ---- Runtime configuration (settings) ----

export type SettingType = 'string' | 'bool' | 'int' | 'select' | 'secret' | 'json'

export interface SettingItem {
  key: string
  group: string
  type: SettingType
  label: string
  help: string | null
  options: string[]
  is_secret: boolean
  /** Whether an explicit value is stored at this scope (vs inherited/default). */
  is_set: boolean
  /** Effective value; omitted for secrets (write-only). */
  value?: unknown
}

export interface SettingChange {
  key: string
  value: unknown
}
