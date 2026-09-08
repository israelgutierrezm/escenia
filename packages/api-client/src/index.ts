import type {
  AccessTokenPayload,
  AdmitParticipantResult,
  ApiCollection,
  ApiErrorPayload,
  ApiResource,
  CapabilityKey,
  EventCapability,
  EventModel,
  EventStatus,
  EventTemplate,
  EventTypeKey,
  GuestJoinResult,
  GuestLinkCreated,
  ParticipantRole,
  ParticipantStage,
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
  }
}

export type ApiClient = ReturnType<typeof createApiClient>
