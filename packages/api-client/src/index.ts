import type {
  AccessTokenPayload,
  AdmitParticipantResult,
  ApiCollection,
  ApiErrorPayload,
  ApiResource,
  AnalyticsSummary,
  AttendanceTimeline,
  Attribution,
  AttributionReport,
  AttendeeRegistered,
  AttendeeSession,
  CapabilityKey,
  EngagementReport,
  ChatMessage,
  EventCapability,
  EventModel,
  EventResource,
  EventStatus,
  EventTemplate,
  EventTypeKey,
  BrandKit,
  BroadcastHealth,
  BroadcastSession,
  DestinationProtocol,
  GuestJoinResult,
  GuestLinkCreated,
  ParticipantRole,
  ParticipantStage,
  Poll,
  PublicRegistration,
  Question,
  Registrant,
  RegistrationField,
  RegistrationForm,
  RunOfShowItem,
  Scene,
  SceneVersion,
  StreamDestination,
  Studio,
  StudioParticipant,
  Tenant,
  User,
  Workspace,
} from '@escenia/types'

export interface ApiClientOptions {
  /** Backend origin. Empty string uses the current origin (dev proxy). */
  baseUrl?: string
  /** Resolver for the active tenant's public ULID (sent as X-Tenant-Id). */
  tenantId?: () => string | null
}

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    message: string,
    public readonly code: string,
    public readonly errors?: Record<string, string[]>,
    public readonly requestId?: string,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
  tenant_name: string
}

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

function readCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'))
  return match?.[1] ? decodeURIComponent(match[1]) : null
}

export function createApiClient(options: ApiClientOptions = {}) {
  const baseUrl = options.baseUrl ?? ''

  async function ensureCsrfCookie(): Promise<void> {
    // Sanctum stateful cookie mode: obtain the XSRF-TOKEN cookie before writes.
    await fetch(`${baseUrl}/sanctum/csrf-cookie`, { credentials: 'include' })
  }

  async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
    const headers: Record<string, string> = { Accept: 'application/json' }

    if (method !== 'GET') {
      await ensureCsrfCookie()
      const token = readCookie('XSRF-TOKEN')
      if (token) headers['X-XSRF-TOKEN'] = token
      if (body !== undefined) headers['Content-Type'] = 'application/json'
    }

    const tenantId = options.tenantId?.()
    if (tenantId) headers['X-Tenant-Id'] = tenantId

    const response = await fetch(`${baseUrl}/api/v1${path}`, {
      method,
      credentials: 'include',
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    })

    if (response.status === 204) {
      return undefined as T
    }

    const payload = (await response.json().catch(() => ({}))) as ApiErrorPayload & Record<string, unknown>

    if (!response.ok) {
      throw new ApiError(
        response.status,
        payload.message ?? 'Request failed',
        payload.error_code ?? 'error',
        payload.errors,
        payload.request_id,
      )
    }

    return payload as T
  }

  return {
    register: (data: RegisterPayload) => request<ApiResource<User>>('POST', '/auth/register', data),
    login: (data: LoginPayload) => request<ApiResource<User>>('POST', '/auth/login', data),
    logout: () => request<void>('POST', '/auth/logout'),
    me: () => request<ApiResource<User>>('GET', '/auth/me'),
    tenants: () => request<ApiCollection<Tenant>>('GET', '/tenants'),
    workspaces: () => request<ApiCollection<Workspace>>('GET', '/workspaces'),
    createWorkspace: (data: { name: string; slug?: string }) =>
      request<ApiResource<Workspace>>('POST', '/workspaces', data),

    // ---- Events ----
    events: (workspace?: string) =>
      request<ApiCollection<EventModel>>(
        'GET',
        workspace ? `/events?workspace=${encodeURIComponent(workspace)}` : '/events',
      ),
    event: (id: string) => request<ApiResource<EventModel>>('GET', `/events/${id}`),
    createEvent: (data: {
      workspace_id: string
      title: string
      type?: EventTypeKey
      slug?: string
      description?: string
      timezone?: string
      scheduled_start_at?: string
      scheduled_end_at?: string
      template_id?: string
    }) => request<ApiResource<EventModel>>('POST', '/events', data),
    transitionEvent: (id: string, status: EventStatus) =>
      request<ApiResource<EventModel>>('POST', `/events/${id}/transition`, { status }),
    setEventCapability: (
      id: string,
      capability: CapabilityKey,
      enabled: boolean,
      settings?: Record<string, unknown>,
    ) =>
      request<ApiResource<EventCapability>>('PUT', `/events/${id}/capabilities/${capability}`, {
        enabled,
        settings,
      }),
    eventTemplates: () => request<ApiCollection<EventTemplate>>('GET', '/event-templates'),

    // ---- Studio ----
    studio: (eventId: string) => request<ApiResource<Studio>>('GET', `/events/${eventId}/studio`),
    startStudio: (eventId: string) =>
      request<ApiResource<Studio>>('POST', `/events/${eventId}/studio/start`),
    endStudio: (eventId: string) =>
      request<ApiResource<Studio>>('POST', `/events/${eventId}/studio/end`),
    studioParticipants: (eventId: string) =>
      request<ApiCollection<StudioParticipant>>('GET', `/events/${eventId}/studio/participants`),
    admitParticipant: (eventId: string, data: { name: string; role: ParticipantRole }) =>
      request<ApiResource<AdmitParticipantResult>>('POST', `/events/${eventId}/studio/participants`, data),
    moveParticipant: (participantId: string, stage: ParticipantStage) =>
      request<ApiResource<StudioParticipant>>('POST', `/studio-participants/${participantId}/move`, { stage }),
    participantToken: (participantId: string) =>
      request<ApiResource<{ access: AccessTokenPayload }>>('POST', `/studio-participants/${participantId}/token`),
    createGuestLink: (
      eventId: string,
      data: { name: string; role: ParticipantRole; expires_at?: string; single_use?: boolean; max_uses?: number },
    ) => request<ApiResource<GuestLinkCreated>>('POST', `/events/${eventId}/studio/guest-links`, data),
    revokeGuestLink: (linkId: string) => request<void>('DELETE', `/studio-guest-links/${linkId}`),
    joinStudioAsGuest: (token: string, name: string) =>
      request<ApiResource<GuestJoinResult>>('POST', `/studio/guest/${token}/join`, { name }),

    // ---- Production ----
    scenes: (eventId: string) =>
      request<ApiCollection<Scene>>('GET', `/events/${eventId}/studio/scenes`),
    createScene: (eventId: string, name: string) =>
      request<ApiResource<Scene>>('POST', `/events/${eventId}/studio/scenes`, { name }),
    scene: (sceneId: string) => request<ApiResource<Scene>>('GET', `/scenes/${sceneId}`),
    updateScene: (sceneId: string, definition: Record<string, unknown>) =>
      request<ApiResource<Scene>>('PUT', `/scenes/${sceneId}`, { definition }),
    sceneVersions: (sceneId: string) =>
      request<ApiCollection<SceneVersion>>('GET', `/scenes/${sceneId}/versions`),
    previewScene: (eventId: string, sceneId: string) =>
      request<ApiResource<Studio>>('POST', `/events/${eventId}/studio/preview`, { scene_id: sceneId }),
    takeScene: (eventId: string, sceneId?: string) =>
      request<ApiResource<Studio>>('POST', `/events/${eventId}/studio/take`, sceneId ? { scene_id: sceneId } : {}),
    brandKits: (eventId: string) =>
      request<ApiCollection<BrandKit>>('GET', `/events/${eventId}/studio/brand-kits`),
    createBrandKit: (
      eventId: string,
      data: { name: string; tokens?: Record<string, unknown>; is_default?: boolean },
    ) => request<ApiResource<BrandKit>>('POST', `/events/${eventId}/studio/brand-kits`, data),
    setDefaultBrandKit: (kitId: string) =>
      request<ApiResource<BrandKit>>('POST', `/brand-kits/${kitId}/default`),
    runOfShow: (eventId: string) =>
      request<ApiCollection<RunOfShowItem>>('GET', `/events/${eventId}/studio/run-of-show`),
    addRunOfShowItem: (
      eventId: string,
      data: { title: string; notes?: string; scene_id?: string; duration_seconds?: number },
    ) => request<ApiResource<RunOfShowItem>>('POST', `/events/${eventId}/studio/run-of-show`, data),
    reorderRunOfShow: (eventId: string, items: string[]) =>
      request<ApiCollection<RunOfShowItem>>('POST', `/events/${eventId}/studio/run-of-show/reorder`, { items }),

    // ---- Broadcast ----
    streamDestinations: (eventId: string) =>
      request<ApiCollection<StreamDestination>>('GET', `/events/${eventId}/studio/destinations`),
    createStreamDestination: (
      eventId: string,
      data: { name: string; protocol: DestinationProtocol; url: string; stream_key: string },
    ) => request<ApiResource<StreamDestination>>('POST', `/events/${eventId}/studio/destinations`, data),
    broadcast: (eventId: string) =>
      request<ApiResource<BroadcastSession | null>>('GET', `/events/${eventId}/studio/broadcast`),
    startBroadcast: (eventId: string, destinations: string[], record = false) =>
      request<ApiResource<BroadcastSession>>('POST', `/events/${eventId}/studio/broadcast/start`, {
        destinations,
        record,
      }),
    stopBroadcast: (broadcastId: string) =>
      request<ApiResource<BroadcastSession>>('POST', `/broadcasts/${broadcastId}/stop`),
    reportBroadcastHealth: (broadcastId: string, health: BroadcastHealth) =>
      request<ApiResource<BroadcastSession>>('POST', `/broadcasts/${broadcastId}/health`, { health }),

    // ---- Registration & Engagement host side (Fase 5) ----
    registrationForm: (eventId: string) =>
      request<ApiResource<RegistrationForm | null>>('GET', `/events/${eventId}/registration-form`),
    saveRegistrationForm: (eventId: string, data: { is_open: boolean; fields: RegistrationField[] }) =>
      request<ApiResource<RegistrationForm>>('PUT', `/events/${eventId}/registration-form`, data),
    registrations: (eventId: string) =>
      request<ApiCollection<Registrant>>('GET', `/events/${eventId}/registrations`),

    eventChat: (eventId: string) =>
      request<ApiCollection<ChatMessage>>('GET', `/events/${eventId}/engagement/chat`),
    postEventChat: (eventId: string, body: string) =>
      request<ApiResource<ChatMessage>>('POST', `/events/${eventId}/engagement/chat`, { body }),

    eventQuestions: (eventId: string) =>
      request<ApiCollection<Question>>('GET', `/events/${eventId}/engagement/questions`),
    answerQuestion: (questionId: string, answer: string) =>
      request<ApiResource<Question>>('POST', `/questions/${questionId}/answer`, { answer }),

    eventPolls: (eventId: string) =>
      request<ApiCollection<Poll>>('GET', `/events/${eventId}/engagement/polls`),
    createPoll: (eventId: string, data: { question: string; options: string[] }) =>
      request<ApiResource<Poll>>('POST', `/events/${eventId}/engagement/polls`, data),
    openPoll: (pollId: string) => request<ApiResource<Poll>>('POST', `/polls/${pollId}/open`),
    closePoll: (pollId: string) => request<ApiResource<Poll>>('POST', `/polls/${pollId}/close`),

    eventResources: (eventId: string) =>
      request<ApiCollection<EventResource>>('GET', `/events/${eventId}/engagement/resources`),
    addEventResource: (eventId: string, data: { title: string; url: string }) =>
      request<ApiResource<EventResource>>('POST', `/events/${eventId}/engagement/resources`, data),

    // ---- Analytics host reporting (Fase 6) ----
    analyticsSummary: (eventId: string) =>
      request<ApiResource<AnalyticsSummary>>('GET', `/events/${eventId}/analytics/summary`),
    analyticsAttendance: (eventId: string) =>
      request<ApiResource<AttendanceTimeline>>('GET', `/events/${eventId}/analytics/attendance`),
    analyticsEngagement: (eventId: string) =>
      request<ApiResource<EngagementReport>>('GET', `/events/${eventId}/analytics/engagement`),
    analyticsAttribution: (eventId: string) =>
      request<ApiResource<AttributionReport>>('GET', `/events/${eventId}/analytics/attribution`),
  }
}

export type ApiClient = ReturnType<typeof createApiClient>

export interface AttendeeClientOptions {
  /** Backend origin. Empty string uses the current origin (dev proxy). */
  baseUrl?: string
  /** Resolver for the attendee join token (sent as X-Attendee-Token). */
  token?: () => string | null
}

/**
 * The attendee-facing client. Registration is public; every live endpoint is
 * authenticated by the attendee join token (never a cookie session), which the
 * caller supplies via `token`. Kept separate from the host client because it is
 * a different app and a different auth model (see CLAUDE.md §6).
 */
export function createAttendeeClient(options: AttendeeClientOptions = {}) {
  const baseUrl = options.baseUrl ?? ''

  async function ensureCsrfCookie(): Promise<void> {
    await fetch(`${baseUrl}/sanctum/csrf-cookie`, { credentials: 'include' })
  }

  async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
    const headers: Record<string, string> = { Accept: 'application/json' }

    if (method !== 'GET') {
      await ensureCsrfCookie()
      const xsrf = readCookie('XSRF-TOKEN')
      if (xsrf) headers['X-XSRF-TOKEN'] = xsrf
      if (body !== undefined) headers['Content-Type'] = 'application/json'
    }

    const token = options.token?.()
    if (token) headers['X-Attendee-Token'] = token

    const response = await fetch(`${baseUrl}/api/v1${path}`, {
      method,
      credentials: 'include',
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    })

    if (response.status === 204) {
      return undefined as T
    }

    const payload = (await response.json().catch(() => ({}))) as ApiErrorPayload & Record<string, unknown>

    if (!response.ok) {
      throw new ApiError(
        response.status,
        payload.message ?? 'Request failed',
        payload.error_code ?? 'error',
        payload.errors,
        payload.request_id,
      )
    }

    return payload as T
  }

  return {
    // ---- Public registration (no token) ----
    registration: (eventId: string) =>
      request<ApiResource<PublicRegistration>>('GET', `/events/${eventId}/registration`),
    register: (
      eventId: string,
      data: { name: string; email: string; answers?: Record<string, unknown>; attribution?: Attribution },
    ) => request<ApiResource<AttendeeRegistered>>('POST', `/events/${eventId}/register`, data),

    // ---- Attendee live surface (token required) ----
    join: () => request<ApiResource<AttendeeSession>>('POST', '/attend/presence/join'),
    leave: () => request<ApiResource<AttendeeSession | null>>('POST', '/attend/presence/leave'),

    chat: () => request<ApiCollection<ChatMessage>>('GET', '/attend/chat'),
    postChat: (body: string) => request<ApiResource<ChatMessage>>('POST', '/attend/chat', { body }),

    questions: () => request<ApiCollection<Question>>('GET', '/attend/questions'),
    askQuestion: (body: string) => request<ApiResource<Question>>('POST', '/attend/questions', { body }),
    voteQuestion: (questionId: string) =>
      request<ApiResource<Question>>('POST', `/attend/questions/${questionId}/vote`),

    polls: () => request<ApiCollection<Poll>>('GET', '/attend/polls'),
    votePoll: (pollId: string, optionId: string) =>
      request<ApiResource<Poll>>('POST', `/attend/polls/${pollId}/vote`, { option_id: optionId }),

    resources: () => request<ApiCollection<EventResource>>('GET', '/attend/resources'),
    downloadResource: (resourceId: string) =>
      request<ApiResource<EventResource>>('POST', `/attend/resources/${resourceId}/download`),
  }
}

export type AttendeeClient = ReturnType<typeof createAttendeeClient>
