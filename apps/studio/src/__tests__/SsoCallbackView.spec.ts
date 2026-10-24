import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia, type Pinia } from 'pinia'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { ApiError } from '@escenia/api-client'
import type { User } from '@escenia/types'

vi.mock('@/lib/api', () => ({
  api: { me: vi.fn(), ssoComplete: vi.fn() },
  setActiveTenant: vi.fn(),
}))

import { api } from '@/lib/api'
import SsoCallbackView from '@/views/SsoCallbackView.vue'

const ada: User = {
  id: 'usr_1',
  name: 'Ada',
  email: 'ada@acme.com',
  timezone: null,
  locale: null,
  tenants: [],
  created_at: null,
}

let pinia: Pinia
let router: Router

async function returnFromIdp(): Promise<VueWrapper> {
  const empty = { render: () => null }
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/sso/:connection/callback', name: 'sso-callback', component: empty },
      { path: '/login', name: 'login', component: empty },
      { path: '/', name: 'events', component: empty },
    ],
  })
  await router.push('/sso/con_1/callback?code=c0de&state=s1')
  const wrapper = mount(SsoCallbackView, { global: { plugins: [pinia, router] } })
  await flushPromises()
  return wrapper
}

describe('studio SsoCallbackView', () => {
  beforeEach(() => {
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
    vi.mocked(api.ssoComplete).mockResolvedValue({ data: ada })
  })

  it('opens the console once the code is redeemed and the session is confirmed', async () => {
    vi.mocked(api.me).mockResolvedValue({ data: ada })

    await returnFromIdp()

    expect(api.ssoComplete).toHaveBeenCalledWith('con_1', { code: 'c0de', state: 's1' })
    expect(router.currentRoute.value.name).toBe('events')
  })

  it('says the server is unreachable when the session check fails after the redeem', async () => {
    vi.mocked(api.me).mockRejectedValue(new ApiError(503, 'Service Unavailable', 'unavailable'))

    const wrapper = await returnFromIdp()

    expect(wrapper.get('[role="alert"]').text()).toBe(
      'No pudimos conectar con el servidor. Revisa tu conexión e inténtalo de nuevo en unos segundos.',
    )
    expect(router.currentRoute.value.name).toBe('sso-callback')
  })

  it('reports an expired or invalid attempt when there is no session', async () => {
    vi.mocked(api.me).mockRejectedValue(new ApiError(401, 'Unauthenticated.', 'unauthenticated'))

    const wrapper = await returnFromIdp()

    expect(wrapper.get('[role="alert"]').text()).toBe(
      'No se pudo iniciar sesión con SSO. El intento expiró o no es válido; vuelve a intentarlo.',
    )
  })
})
