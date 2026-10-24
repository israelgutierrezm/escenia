import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

// The guard never renders: stub the views so the live panels (and their
// realtime deps) are not loaded by the navigation.
vi.mock('@/views/HomeView.vue', () => ({ default: { render: () => null } }))
vi.mock('@/views/RegisterView.vue', () => ({ default: { render: () => null } }))
vi.mock('@/views/CheckoutView.vue', () => ({ default: { render: () => null } }))
vi.mock('@/views/LiveView.vue', () => ({ default: { render: () => null } }))
vi.mock('@/views/VerifyCertificateView.vue', () => ({ default: { render: () => null } }))

import { getAttendeeToken, setAttendeeToken } from '@/lib/attendeeApi'
import { router } from '@/router'

function saveSession(eventId: string, token: string): void {
  localStorage.setItem(
    `escenia.attendee.${eventId}`,
    JSON.stringify({ token, attendee: { id: `att_${eventId}`, name: 'Ada', email: null } }),
  )
}

// The router is a module singleton: every test pushes a path different from
// where the previous one ended (a push to the current location skips guards).
describe('attendee router guard', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    setAttendeeToken(null)
  })

  it('sends an unregistered visitor from the live room to the registration form', async () => {
    await router.push('/e/evt_a/vivo')

    expect(router.currentRoute.value.name).toBe('register')
    expect(router.currentRoute.value.params.eventId).toBe('evt_a')
    expect(document.title).toBe('Registro · Escenia')
  })

  it('skips the form for an attendee already registered to that event', async () => {
    saveSession('evt_b', 'tok_b')

    await router.push('/e/evt_b')

    expect(router.currentRoute.value.name).toBe('live')
    expect(getAttendeeToken()).toBe('tok_b')
    expect(document.title).toBe('En vivo · Escenia')
  })

  it('does not reuse a token saved for another event', async () => {
    saveSession('evt_c', 'tok_c')

    await router.push('/e/evt_d/vivo')

    expect(router.currentRoute.value.name).toBe('register')
    expect(getAttendeeToken()).toBeNull()
  })

  it('keeps checkout and certificate verification public', async () => {
    await router.push('/e/evt_e/entradas')
    expect(router.currentRoute.value.name).toBe('checkout')

    await router.push('/verificar/CERT-123')
    expect(router.currentRoute.value.name).toBe('verify')
    expect(document.title).toBe('Verificar certificado · Escenia')
  })
})
