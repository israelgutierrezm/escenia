import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ApiError } from '@escenia/api-client'

vi.mock('@/lib/api', () => ({
  api: { me: vi.fn(), login: vi.fn(), logout: vi.fn() },
  setActiveTenant: vi.fn(),
}))
// The guard never renders: stub the route components so a navigation does not
// load the console (and with it LiveKit/Echo).
vi.mock('@/views/LoginView.vue', () => ({ default: { render: () => null } }))
vi.mock('@/views/SsoCallbackView.vue', () => ({ default: { render: () => null } }))
vi.mock('@/views/EventsView.vue', () => ({ default: { render: () => null } }))
vi.mock('@/views/ConsoleView.vue', () => ({ default: { render: () => null } }))

import { api } from '@/lib/api'
import { router } from '@/router'
import { useAuthStore } from '@/stores/auth'

const unauthenticated = (): ApiError => new ApiError(401, 'Unauthenticated.', 'unauthenticated')

// The router is a module singleton: every test pushes a path different from
// where the previous one ended (a push to the current location skips guards).
describe('studio router guard', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('checks the session once and sends an anonymous producer to login', async () => {
    vi.mocked(api.me).mockRejectedValue(unauthenticated())

    await router.push('/studio/evt_1')

    expect(router.currentRoute.value.name).toBe('login')
    expect(api.me).toHaveBeenCalledTimes(1)
    expect(document.title).toBe('Entrar · Escenia Studio')
  })

  it('lets a signed-in producer into the console and away from /login', async () => {
    vi.mocked(api.me).mockResolvedValue({
      data: {
        id: 'usr_1',
        name: 'Ada',
        email: 'ada@example.com',
        timezone: null,
        locale: null,
        tenants: [],
        created_at: null,
      },
    })

    await router.push('/studio/evt_2')
    expect(router.currentRoute.value.name).toBe('console')
    expect(document.title).toBe('Consola · Escenia Studio')

    await router.push('/login')
    expect(router.currentRoute.value.name).toBe('events')
    expect(api.me).toHaveBeenCalledTimes(1)
  })

  it('keeps the SSO callback reachable without a session', async () => {
    vi.mocked(api.me).mockRejectedValue(unauthenticated())

    await router.push('/sso/con_1/callback?code=c0de&state=s1')

    expect(router.currentRoute.value.name).toBe('sso-callback')
    expect(router.currentRoute.value.query).toEqual({ code: 'c0de', state: 's1' })
    expect(document.title).toBe('Escenia · Studio')
  })

  it('still reaches the login screen when the API is unreachable (5xx)', async () => {
    vi.mocked(api.me).mockRejectedValue(new ApiError(503, 'Service Unavailable', 'unavailable'))

    await router.push('/studio/evt_3')

    expect(router.currentRoute.value.name).toBe('login')
    expect(useAuthStore().unreachable).toBe(true)
    expect(document.title).toBe('Entrar · Escenia Studio')
  })
})
