import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

// RoomView pulls LiveKit; the guard never renders, so stub every view.
vi.mock('@/views/HomeView.vue', () => ({ default: { render: () => null } }))
vi.mock('@/views/JoinView.vue', () => ({ default: { render: () => null } }))
vi.mock('@/views/RoomView.vue', () => ({ default: { render: () => null } }))

import { router } from '@/router'

function saveSession(linkToken: string): void {
  // The guard only checks that a session exists for the link.
  localStorage.setItem(
    `escenia.speaker.${linkToken}`,
    JSON.stringify({ linkToken, room: 'evt-room' }),
  )
}

// The router is a module singleton: every test pushes a path different from
// where the previous one ended (a push to the current location skips guards).
describe('speaker router guard', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('sends a guest without a session from the room back to the join screen', async () => {
    await router.push('/g/lnk_a/sala')

    expect(router.currentRoute.value.name).toBe('join')
    expect(router.currentRoute.value.params.token).toBe('lnk_a')
    expect(document.title).toBe('Unirme · Escenia')
  })

  it('takes a guest with a saved session straight to the room', async () => {
    saveSession('lnk_b')

    await router.push('/g/lnk_b')

    expect(router.currentRoute.value.name).toBe('room')
    expect(document.title).toBe('Sala · Escenia')
  })

  it('scopes the saved session to its own link', async () => {
    saveSession('lnk_c')

    await router.push('/g/lnk_d/sala')

    expect(router.currentRoute.value.name).toBe('join')
  })

  it('redirects unknown paths home', async () => {
    await router.push('/no-existe')

    expect(router.currentRoute.value.name).toBe('home')
    expect(document.title).toBe('Ponente · Escenia')
  })
})
