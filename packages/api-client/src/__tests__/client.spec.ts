import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { ApiError, createApiClient, createAttendeeClient } from '../index'

// Vitest's default `node` environment: no DOM, so `fetch` and the single
// `document.cookie` read (XSRF-TOKEN) are stubbed per test.
const fetchMock = vi.fn<typeof fetch>()
const XSRF_COOKIE = 'theme=dark; XSRF-TOKEN=eyJpdiI6InRvayJ9%3D%3D'
const XSRF_HEADER = 'eyJpdiI6InRvayJ9=='

interface FetchCall {
  url: string
  init: RequestInit
  headers: Record<string, string>
}

function fetchCall(index: number): FetchCall {
  const args = fetchMock.mock.calls[index]
  if (args === undefined) throw new Error(`fetch call #${index} was not made`)
  const init = args[1] ?? {}
  return { url: String(args[0]), init, headers: (init.headers ?? {}) as Record<string, string> }
}

function json(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

/** API calls get a fresh `api()` response; the Sanctum CSRF round-trip answers 204. */
function respondWith(api: () => Response): void {
  fetchMock.mockImplementation(async (input) =>
    String(input).endsWith('/sanctum/csrf-cookie') ? new Response(null, { status: 204 }) : api(),
  )
}

beforeEach(() => {
  fetchMock.mockReset()
  vi.stubGlobal('fetch', fetchMock)
  vi.stubGlobal('document', { cookie: XSRF_COOKIE })
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('createApiClient', () => {
  it('sends reads with cookies, JSON accept and the active tenant, without a CSRF round-trip', async () => {
    respondWith(() => json(200, { data: [] }))
    const api = createApiClient({
      baseUrl: 'https://api.escenia.test',
      tenantId: () => 'ten_01',
    })

    await api.tenants()

    expect(fetchMock).toHaveBeenCalledTimes(1)
    const { url, init, headers } = fetchCall(0)
    expect(url).toBe('https://api.escenia.test/api/v1/tenants')
    expect(init).toMatchObject({ method: 'GET', credentials: 'include', body: undefined })
    expect(headers).toEqual({ Accept: 'application/json', 'X-Tenant-Id': 'ten_01' })
  })

  it('primes the Sanctum CSRF cookie before a write and echoes it decoded as X-XSRF-TOKEN', async () => {
    respondWith(() => json(200, { data: { id: 'usr_1' } }))
    const api = createApiClient({ baseUrl: 'https://api.escenia.test' })

    await api.login({ email: 'ada@example.com', password: 'secret' })

    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(fetchCall(0).url).toBe('https://api.escenia.test/sanctum/csrf-cookie')
    expect(fetchCall(0).init).toEqual({ credentials: 'include' })
    const { url, init, headers } = fetchCall(1)
    expect(url).toBe('https://api.escenia.test/api/v1/auth/login')
    expect(init.method).toBe('POST')
    expect(headers).toEqual({
      Accept: 'application/json',
      'X-XSRF-TOKEN': XSRF_HEADER,
      'Content-Type': 'application/json',
    })
    expect(init.body).toBe(JSON.stringify({ email: 'ada@example.com', password: 'secret' }))
  })

  it('sends body-less writes without Content-Type, and no X-XSRF-TOKEN without the cookie', async () => {
    vi.stubGlobal('document', { cookie: '' })
    respondWith(() => json(200, { data: {} }))
    const api = createApiClient({ tenantId: () => 'ten_01' })

    await api.startStudio('evt_1')

    const { url, init, headers } = fetchCall(1)
    expect(url).toBe('/api/v1/events/evt_1/studio/start')
    expect(headers).toEqual({ Accept: 'application/json', 'X-Tenant-Id': 'ten_01' })
    expect(init.body).toBeUndefined()
  })

  it('resolves the tenant on every request and omits the header when there is none', async () => {
    respondWith(() => json(200, { data: {} }))
    let tenant: string | null = null
    const api = createApiClient({ tenantId: () => tenant })

    await api.me()
    tenant = 'ten_02'
    await api.me()

    expect(fetchCall(0).headers).not.toHaveProperty('X-Tenant-Id')
    expect(fetchCall(1).headers['X-Tenant-Id']).toBe('ten_02')
  })

  it('resolves 204 No Content as undefined (logout with the SLO return_to)', async () => {
    respondWith(() => new Response(null, { status: 204 }))
    const api = createApiClient()

    await expect(api.logout('https://studio.escenia.test/login')).resolves.toBeUndefined()
    expect(fetchCall(1).init.body).toBe(
      JSON.stringify({ return_to: 'https://studio.escenia.test/login' }),
    )
  })

  it('maps the error envelope to an ApiError', async () => {
    respondWith(() =>
      json(422, {
        message: 'The given data was invalid.',
        error_code: 'validation_failed',
        errors: { email: ['The email field is required.'] },
        request_id: 'req_123',
      }),
    )

    const error = await createApiClient()
      .login({ email: '', password: '' })
      .catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    const apiError = error as ApiError
    expect(apiError.name).toBe('ApiError')
    expect(apiError.status).toBe(422)
    expect(apiError.message).toBe('The given data was invalid.')
    expect(apiError.code).toBe('validation_failed')
    expect(apiError.errors).toEqual({ email: ['The email field is required.'] })
    expect(apiError.requestId).toBe('req_123')
  })

  it('falls back to a generic ApiError when the error body is not JSON', async () => {
    respondWith(() => new Response('<h1>502 Bad Gateway</h1>', { status: 502 }))

    const error = (await createApiClient()
      .me()
      .catch((e: unknown) => e)) as ApiError

    expect(error).toBeInstanceOf(ApiError)
    expect([error.status, error.message, error.code, error.requestId]).toEqual([
      502,
      'Request failed',
      'error',
      undefined,
    ])
  })

  it('URL-encodes user input placed in query strings', async () => {
    respondWith(() => json(200, { data: [] }))
    const api = createApiClient()

    await api.ssoDiscover('ana+sso@acme.com')
    await api.ssoStart('con_1', 'https://studio.escenia.test/sso/con_1/callback')

    expect(fetchCall(0).url).toBe('/api/v1/sso/discover?email=ana%2Bsso%40acme.com')
    expect(fetchCall(1).url).toBe(
      '/api/v1/sso/con_1?redirect_uri=https%3A%2F%2Fstudio.escenia.test%2Fsso%2Fcon_1%2Fcallback',
    )
  })

  it('uploads multipart with CSRF and tenant, leaving Content-Type to the runtime', async () => {
    respondWith(() => json(200, { data: { id: 'bk_1' } }))
    const api = createApiClient({ tenantId: () => 'ten_01' })

    await api.uploadBrandKitLogo('bk_1', new File(['png'], 'logo.png', { type: 'image/png' }))

    expect(fetchCall(0).url).toBe('/sanctum/csrf-cookie')
    const { url, init, headers } = fetchCall(1)
    expect(url).toBe('/api/v1/brand-kits/bk_1/logo')
    expect(init.method).toBe('POST')
    expect(headers).toEqual({
      Accept: 'application/json',
      'X-XSRF-TOKEN': XSRF_HEADER,
      'X-Tenant-Id': 'ten_01',
    })
    expect(((init.body as FormData).get('logo') as File).name).toBe('logo.png')
  })
})

describe('createAttendeeClient', () => {
  it('authenticates live calls with X-Attendee-Token and never sends a tenant', async () => {
    respondWith(() => json(200, { data: [] }))
    const api = createAttendeeClient({
      baseUrl: 'https://api.escenia.test',
      token: () => 'tok_att',
    })

    await api.chat()

    const { url, init, headers } = fetchCall(0)
    expect(url).toBe('https://api.escenia.test/api/v1/attend/chat')
    expect(init.credentials).toBe('include')
    expect(headers).toEqual({ Accept: 'application/json', 'X-Attendee-Token': 'tok_att' })
  })

  it('calls public registration endpoints without a token', async () => {
    respondWith(() => json(200, { data: {} }))

    await createAttendeeClient().registration('evt_1')

    expect(fetchCall(0).url).toBe('/api/v1/events/evt_1/registration')
    expect(fetchCall(0).headers).toEqual({ Accept: 'application/json' })
  })

  it('reads the token lazily and adds CSRF + JSON for writes', async () => {
    respondWith(() => json(200, { data: {} }))
    let token: string | null = null
    const api = createAttendeeClient({ token: () => token })
    token = 'tok_late'

    await api.postChat('Hola')

    expect(fetchCall(0).url).toBe('/sanctum/csrf-cookie')
    expect(fetchCall(1).headers).toEqual({
      Accept: 'application/json',
      'X-XSRF-TOKEN': XSRF_HEADER,
      'Content-Type': 'application/json',
      'X-Attendee-Token': 'tok_late',
    })
    expect(fetchCall(1).init.body).toBe(JSON.stringify({ body: 'Hola' }))
  })

  it('maps errors to ApiError like the host client', async () => {
    respondWith(() =>
      json(401, {
        message: 'Unauthenticated.',
        error_code: 'unauthenticated',
        request_id: 'req_9',
      }),
    )

    const error = (await createAttendeeClient({ token: () => 'expired' })
      .polls()
      .catch((e: unknown) => e)) as ApiError

    expect(error).toBeInstanceOf(ApiError)
    expect(error.status).toBe(401)
    expect(error.code).toBe('unauthenticated')
    expect(error.requestId).toBe('req_9')
  })
})
