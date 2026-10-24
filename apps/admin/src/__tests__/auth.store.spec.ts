import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ApiError } from '@escenia/api-client'

vi.mock('@/lib/api', () => ({
  api: {
    me: vi.fn(),
    login: vi.fn(),
    logout: vi.fn(),
  },
  setActiveTenant: vi.fn(),
}))

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('stores the user after fetchUser', async () => {
    vi.mocked(api.me).mockResolvedValue({
      data: {
        id: 'usr_ulid',
        name: 'Ada',
        email: 'ada@example.com',
        timezone: null,
        locale: null,
        tenants: [],
        created_at: null,
      },
    })

    const auth = useAuthStore()
    await auth.fetchUser()

    expect(auth.isAuthenticated).toBe(true)
    expect(auth.user?.email).toBe('ada@example.com')
    expect(auth.ready).toBe(true)
  })

  it('clears the user on a 401 response', async () => {
    vi.mocked(api.me).mockRejectedValue(new ApiError(401, 'Unauthenticated.', 'unauthenticated'))

    const auth = useAuthStore()
    await auth.fetchUser()

    expect(auth.isAuthenticated).toBe(false)
    expect(auth.ready).toBe(true)
    expect(auth.unreachable).toBe(false)
  })

  it.each([
    ['a 5xx', new ApiError(503, 'Service Unavailable', 'unavailable')],
    ['a network failure', new TypeError('Failed to fetch')],
  ])(
    'treats %s as an unreachable API: signed out and ready, without throwing',
    async (_label, failure) => {
      vi.mocked(api.me).mockRejectedValue(failure)

      const auth = useAuthStore()
      await expect(auth.fetchUser()).resolves.toBeUndefined()

      expect(auth.isAuthenticated).toBe(false)
      expect(auth.ready).toBe(true)
      expect(auth.unreachable).toBe(true)
    },
  )

  it('clears unreachable on the next successful session check or login', async () => {
    const ada = {
      id: 'usr_ulid',
      name: 'Ada',
      email: 'ada@example.com',
      timezone: null,
      locale: null,
      tenants: [],
      created_at: null,
    }
    const auth = useAuthStore()
    vi.mocked(api.me).mockRejectedValue(new ApiError(502, 'Bad Gateway', 'error'))
    await auth.fetchUser()
    expect(auth.unreachable).toBe(true)

    vi.mocked(api.me).mockResolvedValue({ data: ada })
    await auth.fetchUser()
    expect(auth.unreachable).toBe(false)

    vi.mocked(api.me).mockRejectedValue(new TypeError('Failed to fetch'))
    await auth.fetchUser()
    expect(auth.unreachable).toBe(true)
    expect(auth.isAuthenticated).toBe(false)

    vi.mocked(api.login).mockResolvedValue({ data: ada })
    await auth.login('ada@example.com', 'secret')
    expect(auth.unreachable).toBe(false)
    expect(auth.isAuthenticated).toBe(true)
  })
})
