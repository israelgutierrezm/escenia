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
