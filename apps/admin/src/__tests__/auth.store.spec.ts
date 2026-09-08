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
  })
})
