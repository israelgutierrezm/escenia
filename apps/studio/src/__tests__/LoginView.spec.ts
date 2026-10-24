import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia, type Pinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { mount, type VueWrapper } from '@vue/test-utils'
import { ApiError } from '@escenia/api-client'

vi.mock('@/lib/api', () => ({
  api: { me: vi.fn(), login: vi.fn(), logout: vi.fn(), ssoDiscover: vi.fn(), ssoStart: vi.fn() },
  setActiveTenant: vi.fn(),
}))

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import LoginView from '@/views/LoginView.vue'

const SIN_SERVIDOR =
  'No pudimos conectar con el servidor. Revisa tu conexión e inténtalo de nuevo en unos segundos.'

let pinia: Pinia

async function mountLogin(): Promise<VueWrapper> {
  const empty = { render: () => null }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', name: 'login', component: empty },
      { path: '/', name: 'events', component: empty },
    ],
  })
  await router.push('/login')
  return mount(LoginView, { global: { plugins: [pinia, router] } })
}

describe('studio LoginView', () => {
  beforeEach(() => {
    pinia = createPinia()
    setActivePinia(pinia)
    vi.clearAllMocks()
  })

  it('warns that the server is unreachable when the session check failed with a 5xx', async () => {
    vi.mocked(api.me).mockRejectedValue(new ApiError(500, 'Server Error', 'error'))
    await useAuthStore().fetchUser()

    const wrapper = await mountLogin()

    expect(wrapper.get('[role="alert"]').text()).toBe(SIN_SERVIDOR)
  })

  it('shows a plain guest the form without the warning', async () => {
    vi.mocked(api.me).mockRejectedValue(new ApiError(401, 'Unauthenticated.', 'unauthenticated'))
    await useAuthStore().fetchUser()

    const wrapper = await mountLogin()

    expect(wrapper.text()).not.toContain(SIN_SERVIDOR)
    expect(wrapper.find('form').exists()).toBe(true)
  })
})
