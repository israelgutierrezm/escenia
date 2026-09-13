import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import type { AccessTokenPayload, StudioParticipant } from '@escenia/types'

import { api } from '@/lib/speakerApi'

interface SpeakerSession {
  linkToken: string
  participant: StudioParticipant
  room: string
  access: AccessTokenPayload
}

function storageKey(linkToken: string): string {
  return `escenia.speaker.${linkToken}`
}

function loadStored(linkToken: string): SpeakerSession | null {
  try {
    const raw = localStorage.getItem(storageKey(linkToken))
    return raw !== null ? (JSON.parse(raw) as SpeakerSession) : null
  } catch {
    return null
  }
}

function saveStored(linkToken: string, data: SpeakerSession): void {
  try {
    localStorage.setItem(storageKey(linkToken), JSON.stringify(data))
  } catch {
    /* almacenamiento no disponible */
  }
}

function clearStored(linkToken: string): void {
  try {
    localStorage.removeItem(storageKey(linkToken))
  } catch {
    /* almacenamiento no disponible */
  }
}

export const useSpeakerStore = defineStore('speaker', () => {
  const session = ref<SpeakerSession | null>(null)

  const isJoined = computed(() => session.value !== null)

  function activate(linkToken: string): void {
    session.value = loadStored(linkToken)
  }

  async function join(linkToken: string, name: string): Promise<void> {
    const res = (await api.joinStudioAsGuest(linkToken, name)).data
    const s: SpeakerSession = {
      linkToken,
      participant: res.participant,
      room: res.session.room !== '' ? res.session.room : res.access.room,
      access: res.access,
    }
    session.value = s
    saveStored(linkToken, s)
  }

  function logout(): void {
    if (session.value !== null) clearStored(session.value.linkToken)
    session.value = null
  }

  return { session, isJoined, activate, join, logout }
})
