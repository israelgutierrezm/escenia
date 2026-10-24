import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import type { Attendee } from '@escenia/types'

import { getAttendeeToken, setAttendeeToken } from '@/lib/attendeeApi'
import { useAttendeeStore } from '@/stores/attendee'

const ada: Attendee = { id: 'att_1', name: 'Ada', email: 'ada@example.com' }
const key = (eventId: string): string => `escenia.attendee.${eventId}`

describe('attendee store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    setAttendeeToken(null)
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('persists a new session per event and hands the token to the API client', () => {
    const store = useAttendeeStore()

    store.setSession('evt_a', { token: 'tok_a', attendee: ada })

    expect(store.isRegistered).toBe(true)
    expect(store.eventId).toBe('evt_a')
    expect(getAttendeeToken()).toBe('tok_a')
    expect(JSON.parse(localStorage.getItem(key('evt_a')) ?? 'null')).toEqual({
      token: 'tok_a',
      attendee: ada,
    })
  })

  it('restores the saved session of the event being opened, and only that one', () => {
    localStorage.setItem(key('evt_a'), JSON.stringify({ token: 'tok_a', attendee: ada }))
    const store = useAttendeeStore()

    store.activate('evt_a')
    expect(store.token).toBe('tok_a')
    expect(store.attendee).toEqual(ada)
    expect(getAttendeeToken()).toBe('tok_a')

    store.activate('evt_b')
    expect(store.isRegistered).toBe(false)
    expect(store.attendee).toBeNull()
    expect(getAttendeeToken()).toBeNull()
  })

  it('treats unreadable saved data as no session', () => {
    localStorage.setItem(key('evt_a'), '{not json')
    const store = useAttendeeStore()

    store.activate('evt_a')

    expect(store.isRegistered).toBe(false)
  })

  it('keeps the session in memory when storage is unavailable', () => {
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('quota exceeded', 'QuotaExceededError')
    })
    const store = useAttendeeStore()

    expect(() => store.setSession('evt_a', { token: 'tok_a', attendee: ada })).not.toThrow()
    expect(store.isRegistered).toBe(true)
    expect(getAttendeeToken()).toBe('tok_a')
  })

  it('logout forgets the current event only', () => {
    localStorage.setItem(key('evt_a'), JSON.stringify({ token: 'tok_a', attendee: ada }))
    localStorage.setItem(key('evt_b'), JSON.stringify({ token: 'tok_b', attendee: ada }))
    const store = useAttendeeStore()
    store.activate('evt_a')

    store.logout()

    expect(localStorage.getItem(key('evt_a'))).toBeNull()
    expect(localStorage.getItem(key('evt_b'))).not.toBeNull()
    expect(store.isRegistered).toBe(false)
    expect(getAttendeeToken()).toBeNull()
  })
})
