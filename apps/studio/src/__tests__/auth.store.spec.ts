import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ApiError } from '@escenia/api-client'
import type { Tenant, User } from '@escenia/types'

vi.mock('@/lib/api', () => ({
  api: { me: vi.fn(), login: vi.fn(), logout: vi.fn() },
  setActiveTenant: vi.fn(),
}))

import { api, setActiveTenant } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

function tenant(id: string): Tenant {
  return { id, name: `Org ${id}`, slug: id, status: 'active', created_at: null }
}

function user(tenants: Tenant[]): User {
  return {
    id: 'usr_1',
    name: 'Ada',
    email: 'ada@example.com',
    timezone: null,
    locale: null,
    tenants,
    created_at: null,
  }
}

describe('studio auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('activates the first membership after fetchUser and tells the API client', async () => {
    vi.mocked(api.me).mockResolvedValue({ data: user([tenant('ten_a'), tenant('ten_b')]) })
    const auth = useAuthStore()

    await auth.fetchUser()

    expect(auth.ready).toBe(true)
    expect(auth.isAuthenticated).toBe(true)
    expect(auth.unreachable).toBe(false)
    expect(auth.activeTenantId).toBe('ten_a')
    expect(auth.activeTenant?.name).toBe('Org ten_a')
    expect(setActiveTenant).toHaveBeenLastCalledWith('ten_a')
  })

  it('keeps a still-valid selection and falls back when that membership is gone', async () => {
    const auth = useAuthStore()
    vi.mocked(api.me).mockResolvedValue({ data: user([tenant('ten_a'), tenant('ten_b')]) })
    await auth.fetchUser()
    auth.selectTenant('ten_b')

    await auth.fetchUser()
    expect(auth.activeTenantId).toBe('ten_b')

    vi.mocked(api.me).mockResolvedValue({ data: user([tenant('ten_a')]) })
    await auth.fetchUser()
    expect(auth.activeTenantId).toBe('ten_a')
    expect(setActiveTenant).toHaveBeenLastCalledWith('ten_a')
  })

  it('selects no tenant for a user without memberships', async () => {
    vi.mocked(api.me).mockResolvedValue({ data: user([]) })
    const auth = useAuthStore()

    await auth.fetchUser()

    expect(auth.activeTenantId).toBeNull()
    expect(auth.activeTenant).toBeNull()
    expect(setActiveTenant).toHaveBeenLastCalledWith(null)
  })

  it('treats a 401 as a plain guest', async () => {
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

      expect(auth.ready).toBe(true)
      expect(auth.isAuthenticated).toBe(false)
      expect(auth.unreachable).toBe(true)
    },
  )

  it('clears unreachable on the next successful session check or login', async () => {
    const auth = useAuthStore()
    vi.mocked(api.me).mockRejectedValue(new ApiError(502, 'Bad Gateway', 'error'))
    await auth.fetchUser()
    expect(auth.unreachable).toBe(true)

    vi.mocked(api.me).mockResolvedValue({ data: user([tenant('ten_a')]) })
    await auth.fetchUser()
    expect(auth.unreachable).toBe(false)

    vi.mocked(api.me).mockRejectedValue(new TypeError('Failed to fetch'))
    await auth.fetchUser()
    expect(auth.unreachable).toBe(true)
    expect(auth.isAuthenticated).toBe(false)

    vi.mocked(api.login).mockResolvedValue({ data: user([tenant('ten_a')]) })
    await auth.login('ada@example.com', 'secret')
    expect(auth.unreachable).toBe(false)
    expect(auth.isAuthenticated).toBe(true)
  })

  it('login stores the user and activates the default tenant', async () => {
    vi.mocked(api.login).mockResolvedValue({ data: user([tenant('ten_a')]) })
    const auth = useAuthStore()

    await auth.login('ada@example.com', 'secret')

    expect(api.login).toHaveBeenCalledWith({ email: 'ada@example.com', password: 'secret' })
    expect(auth.activeTenantId).toBe('ten_a')
  })

  it('logout returns the SAML single-logout URL and forgets user and tenant', async () => {
    vi.mocked(api.me).mockResolvedValue({ data: user([tenant('ten_a')]) })
    vi.mocked(api.logout).mockResolvedValue({
      data: { sso_logout_url: 'https://idp.acme.test/slo?SAMLRequest=x' },
    })
    const auth = useAuthStore()
    await auth.fetchUser()

    await expect(auth.logout()).resolves.toBe('https://idp.acme.test/slo?SAMLRequest=x')

    expect(api.logout).toHaveBeenCalledWith(`${window.location.origin}/login`)
    expect(auth.user).toBeNull()
    expect(auth.activeTenantId).toBeNull()
    expect(setActiveTenant).toHaveBeenLastCalledWith(null)
  })

  it('logout returns null for a plain cookie session (204)', async () => {
    vi.mocked(api.logout).mockResolvedValue(undefined)

    await expect(useAuthStore().logout()).resolves.toBeNull()
  })
})
