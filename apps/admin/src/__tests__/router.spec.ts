import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ApiError } from '@escenia/api-client'
import type { User } from '@escenia/types'

vi.mock('@/lib/api', () => ({
  api: { me: vi.fn(), login: vi.fn(), logout: vi.fn() },
  setActiveTenant: vi.fn(),
}))
// The guard never renders: stub the components the navigations below resolve
// (the login frame needs WebGL, the layout loads the whole console).
vi.mock('@/views/LoginView.vue', () => ({ default: { render: () => null } }))
vi.mock('@/layouts/AdminLayout.vue', () => ({ default: { render: () => null } }))
vi.mock('@/views/DashboardView.vue', () => ({ default: { render: () => null } }))

import { api } from '@/lib/api'
import { router } from '@/router'
import { useAuthStore } from '@/stores/auth'

function member(isSuperAdmin: boolean): User {
  return {
    id: 'usr_1',
    name: 'Ada',
    email: 'ada@example.com',
    is_super_admin: isSuperAdmin,
    timezone: null,
    locale: null,
    tenants: [],
    created_at: null,
  }
}

// The router is a module singleton: every test pushes a path different from
// where the previous one ended (a push to the current location skips guards).
describe('admin router guard', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('sends a guest to login', async () => {
    vi.mocked(api.me).mockRejectedValue(new ApiError(401, 'Unauthenticated.', 'unauthenticated'))

    await router.push('/events')

    expect(router.currentRoute.value.name).toBe('login')
    expect(document.title).toBe('Iniciar sesión · Escenia')
  })

  it('keeps the system settings to super-admins', async () => {
    vi.mocked(api.me).mockResolvedValue({ data: member(false) })

    await router.push('/system-settings')

    expect(router.currentRoute.value.name).toBe('dashboard')
    expect(api.me).toHaveBeenCalledTimes(1)
  })

  it('still reaches the login screen when the API is unreachable (5xx)', async () => {
    vi.mocked(api.me).mockRejectedValue(new ApiError(503, 'Service Unavailable', 'unavailable'))

    await router.push('/members')

    expect(router.currentRoute.value.name).toBe('login')
    expect(useAuthStore().unreachable).toBe(true)
    expect(document.title).toBe('Iniciar sesión · Escenia')
  })
})
