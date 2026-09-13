import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import type { Attendee } from '@escenia/types'

import { setAttendeeToken } from '@/lib/attendeeApi'

interface StoredSession {
  token: string
  attendee: Attendee
}

function storageKey(eventId: string): string {
  return `escenia.attendee.${eventId}`
}

function loadStored(eventId: string): StoredSession | null {
  try {
    const raw = localStorage.getItem(storageKey(eventId))
    return raw !== null ? (JSON.parse(raw) as StoredSession) : null
  } catch {
    return null
  }
}

function saveStored(eventId: string, data: StoredSession): void {
  try {
    localStorage.setItem(storageKey(eventId), JSON.stringify(data))
  } catch {
    /* almacenamiento no disponible */
  }
}

function clearStored(eventId: string): void {
  try {
    localStorage.removeItem(storageKey(eventId))
  } catch {
    /* almacenamiento no disponible */
  }
}

/**
 * The attendee session is scoped to a single event and authenticated by a join
 * token (never a cookie). The token is persisted in localStorage per event so a
 * returning attendee stays in without re-registering.
 */
export const useAttendeeStore = defineStore('attendee', () => {
  const eventId = ref<string | null>(null)
  const token = ref<string | null>(null)
  const attendee = ref<Attendee | null>(null)

  const isRegistered = computed(() => token.value !== null)

  /** Point the store (and the API client) at an event, loading any saved token. */
  function activate(id: string): void {
    eventId.value = id
    const stored = loadStored(id)
    token.value = stored?.token ?? null
    attendee.value = stored?.attendee ?? null
    setAttendeeToken(token.value)
  }

  /** Persist a freshly-issued session after registration. */
  function setSession(id: string, data: StoredSession): void {
    eventId.value = id
    token.value = data.token
    attendee.value = data.attendee
    setAttendeeToken(data.token)
    saveStored(id, data)
  }

  function logout(): void {
    if (eventId.value !== null) clearStored(eventId.value)
    token.value = null
    attendee.value = null
    setAttendeeToken(null)
  }

  return { eventId, token, attendee, isRegistered, activate, setSession, logout }
})
