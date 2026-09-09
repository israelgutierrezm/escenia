import type {
  AccessTokenPayload,
  AdmitParticipantResult,
  ApiCollection,
  ApiErrorPayload,
  ApiResource,
  AnalyticsSummary,
  Assessment,
  AssessmentQuestionType,
  AssessmentSubmission,
  AttendanceTimeline,
  Automation,
  Certificate,
  CertificateVerification,
  CompletionRule,
  AutomationRun,
  AutomationStepInput,
  Clip,
  Condition,
  ContentSummary,
  EventPlan,
  QaAnswer,
  Recording,
  RecordingUpload,
  SearchHit,
  SummaryKind,
  Transcript,
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
  CheckoutResult,
  Cta,
  DestinationProtocol,
  Order,
  PaymentAccount,
  PaymentGatewayName,
  RevenueReport,
  Ticket,
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

    // ---- Commerce host side (Fase 7) ----
    tickets: (eventId: string) =>
      request<ApiCollection<Ticket>>('GET', `/events/${eventId}/commerce/tickets`),
    createTicket: (
      eventId: string,
      data: {
        name: string
        amount_minor: number
        currency: string
        description?: string
        compare_at_minor?: number
        capacity?: number
        sales_start_at?: string
        sales_end_at?: string
      },
    ) => request<ApiResource<Ticket>>('POST', `/events/${eventId}/commerce/tickets`, data),
    orders: (eventId: string) =>
      request<ApiCollection<Order>>('GET', `/events/${eventId}/commerce/orders`),
    ctas: (eventId: string) =>
      request<ApiCollection<Cta>>('GET', `/events/${eventId}/commerce/ctas`),
    createCta: (
      eventId: string,
      data: { title: string; body?: string; url?: string; ticket?: string; starts_at?: string; ends_at?: string },
    ) => request<ApiResource<Cta>>('POST', `/events/${eventId}/commerce/ctas`, data),
    revenue: (eventId: string) =>
      request<ApiResource<RevenueReport>>('GET', `/events/${eventId}/commerce/revenue`),
    paymentAccounts: () => request<ApiCollection<PaymentAccount>>('GET', '/payment-accounts'),
    savePaymentAccount: (data: {
      gateway: PaymentGatewayName
      display_name: string
      currency: string
      credentials?: Record<string, string>
      webhook_secret?: string
      is_active?: boolean
    }) => request<ApiResource<PaymentAccount>>('PUT', '/payment-accounts', data),

    // ---- Automation host side (Fase 8) ----
    automations: () => request<ApiCollection<Automation>>('GET', '/automations'),
    createAutomation: (data: {
      name: string
      trigger: Automation['trigger']
      conditions?: Condition[]
      steps: AutomationStepInput[]
    }) => request<ApiResource<Automation>>('POST', '/automations', data),
    setAutomationActive: (automationId: string, isActive: boolean) =>
      request<ApiResource<Automation>>('POST', `/automations/${automationId}/active`, { is_active: isActive }),
    automationRuns: (automationId: string) =>
      request<ApiCollection<AutomationRun>>('GET', `/automations/${automationId}/runs`),

    // ---- Content: recordings, transcripts, clips (Fase 9) ----
    recordings: (eventId: string) =>
      request<ApiCollection<Recording>>('GET', `/events/${eventId}/recordings`),
    requestRecordingUpload: (eventId: string, data: { content_type: string; title?: string }) =>
      request<ApiResource<RecordingUpload>>('POST', `/events/${eventId}/recordings`, data),
    recording: (recordingId: string) =>
      request<ApiResource<Recording>>('GET', `/recordings/${recordingId}`),
    completeRecording: (
      recordingId: string,
      data: { duration_ms?: number; size_bytes?: number; format?: string },
    ) => request<ApiResource<Recording>>('POST', `/recordings/${recordingId}/complete`, data),
    transcribeRecording: (recordingId: string, language = 'en') =>
      request<ApiResource<Transcript>>('POST', `/recordings/${recordingId}/transcribe`, { language }),
    transcript: (transcriptId: string) =>
      request<ApiResource<Transcript>>('GET', `/transcripts/${transcriptId}`),
    clips: (recordingId: string) =>
      request<ApiCollection<Clip>>('GET', `/recordings/${recordingId}/clips`),
    createClip: (recordingId: string, data: { title: string; start_ms: number; end_ms: number }) =>
      request<ApiResource<Clip>>('POST', `/recordings/${recordingId}/clips`, data),

    // ---- AI: semantic search, Q&A, summaries, architect (Fase 10) ----
    searchContent: (eventId: string, q: string, limit?: number) =>
      request<ApiCollection<SearchHit>>(
        'GET',
        `/events/${eventId}/content/search?q=${encodeURIComponent(q)}${limit ? `&limit=${limit}` : ''}`,
      ),
    askContent: (eventId: string, question: string) =>
      request<ApiResource<QaAnswer>>('POST', `/events/${eventId}/content/ask`, { question }),
    requestSummary: (recordingId: string, kind: SummaryKind) =>
      request<ApiResource<ContentSummary>>('POST', `/recordings/${recordingId}/summaries`, { kind }),
    contentSummary: (summaryId: string) =>
      request<ApiResource<ContentSummary>>('GET', `/summaries/${summaryId}`),
    designEvent: (eventId: string, brief: string) =>
      request<ApiResource<EventPlan>>('POST', `/events/${eventId}/ai/architect`, { brief }),

    // ---- Education host side (Fase 11) ----
    assessment: (eventId: string) =>
      request<ApiResource<Assessment | null>>('GET', `/events/${eventId}/education/assessment`),
    saveAssessment: (
      eventId: string,
      data: {
        title: string
        passing_score: number
        is_published?: boolean
        questions: Array<{
          prompt: string
          type: AssessmentQuestionType
          points?: number
          options: Array<{ key: string; label: string; correct?: boolean }>
        }>
      },
    ) => request<ApiResource<Assessment>>('PUT', `/events/${eventId}/education/assessment`, data),
    completionRule: (eventId: string) =>
      request<ApiResource<CompletionRule | null>>('GET', `/events/${eventId}/education/completion-rule`),
    saveCompletionRule: (eventId: string, data: CompletionRule) =>
      request<ApiResource<CompletionRule>>('PUT', `/events/${eventId}/education/completion-rule`, data),
    submissions: (eventId: string) =>
      request<ApiCollection<AssessmentSubmission>>('GET', `/events/${eventId}/education/submissions`),
    certificates: (eventId: string) =>
      request<ApiCollection<Certificate>>('GET', `/events/${eventId}/education/certificates`),
    issueCertificates: (eventId: string) =>
      request<ApiResource<{ issued: number }>>('POST', `/events/${eventId}/education/certificates/issue`),
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

    // ---- Commerce: public checkout (no token) ----
    tickets: (eventId: string) => request<ApiCollection<Ticket>>('GET', `/events/${eventId}/tickets`),
    checkout: (
      eventId: string,
      data: { buyer_name: string; buyer_email: string; items: Array<{ ticket: string; quantity: number }> },
    ) => request<ApiResource<CheckoutResult>>('POST', `/events/${eventId}/checkout`, data),

    // ---- Commerce: attendee CTAs (token required) ----
    ctas: () => request<ApiCollection<Cta>>('GET', '/attend/ctas'),
    clickCta: (ctaId: string) => request<ApiResource<Cta>>('POST', `/attend/ctas/${ctaId}/click`),

    // ---- Education: assessment (token) + public certificate verification ----
    assessment: () => request<ApiResource<Assessment | null>>('GET', '/attend/assessment'),
    submitAssessment: (answers: Record<string, string | string[]>) =>
      request<ApiResource<AssessmentSubmission>>('POST', '/attend/assessment', { answers }),
    verifyCertificate: (code: string) =>
      request<ApiResource<CertificateVerification>>('GET', `/certificates/verify/${code}`),
  }
}

export type AttendeeClient = ReturnType<typeof createAttendeeClient>
